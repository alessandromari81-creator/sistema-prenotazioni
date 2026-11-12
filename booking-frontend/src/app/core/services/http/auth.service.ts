import { Injectable, InjectionToken, computed, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import Keycloak from 'keycloak-js';
import { environment } from '../../../../environments/environment';
import { User } from '../../models/models';
import { NotificationService } from '../features/notification.service';
import { SessionMessage, SessionSyncService } from '../features/session-sync.service';

/**
 * Il client di Keycloak. Sta in un token di iniezione così nei test si può sostituire con uno finto,
 * senza un server vero.
 */
export const KEYCLOAK = new InjectionToken<Keycloak>('Client di Keycloak', {
  providedIn: 'root',
  factory: () => new Keycloak(environment.keycloak),
});

/**
 * Gestisce l'accesso dell'utente.
 *
 * Login, registrazione e password le gestisce Keycloak: l'app non vede mai una password. Il flusso è
 * "Authorization Code + PKCE": il browser va alla pagina di accesso di Keycloak, poi torna con un codice che
 * keycloak-js scambia con un token (JWT). Quel token si manda a Laravel nell'header Authorization (vedi l'interceptor).
 * Laravel conosce il profilo applicativo dell'utente (ruolo, se insegna...), che si legge da GET /api/user.
 */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private http = inject(HttpClient);
  private keycloak = inject(KEYCLOAK);
  private sessionSync = inject(SessionSyncService);
  private notifications = inject(NotificationService);
  private router = inject(Router);

  // L'unico stato è il profilo dell'utente: il token lo tiene keycloak-js in memoria
  readonly user = signal<User | null>(null);
  readonly isLoggedIn = computed(() => this.user() !== null);
  readonly isAdmin = computed(() => this.user()?.role === 'admin');
  readonly teachesCourses = computed(() => this.user()?.teaches_courses === true);

  /**
   * L'esito dell'ultima azione chiesta a Keycloak (cambio password, modifica del nome), quando si torna da lì:
   * "success", "cancelled" oppure "error". Resta null se non c'è stata nessuna azione.
   */
  readonly actionResult = signal<{ status: string; action?: string } | null>(null);

  /**
   * Eseguita all'avvio dell'app, prima di mostrare qualsiasi pagina. Controlla se l'utente ha già una sessione
   * su Keycloak (o se sta tornando dalla pagina di accesso) e, se sì, carica il suo profilo.
   * Se Keycloak non risponde l'app parte comunque, come se l'utente non fosse collegato.
   */
  async init(): Promise<void> {
    // Quando l'utente torna da un'azione chiesta a Keycloak, keycloak-js ci avvisa con l'esito
    this.keycloak.onActionUpdate = (status, action) => this.actionResult.set({ status, action });

    // Ascolta le altre schede: se una fa login o logout, questa si allinea (vedi onOtherTabMessage)
    this.sessionSync.listen((message) => this.onOtherTabMessage(message));

    try {
      const authenticated = await this.keycloak.init({
        onLoad: 'check-sso',
        // Il controllo avviene in un iframe nascosto: la pagina non viene ricaricata se l'utente non ha una sessione
        silentCheckSsoRedirectUri: `${window.location.origin}/silent-check-sso.html`,
        pkceMethod: 'S256',
        checkLoginIframe: false,
      });

      if (authenticated) {
        await this.loadProfile();
        // Le altre schede confrontano questo id con il proprio: se è lo stesso utente non fanno nulla
        this.sessionSync.announce({ type: 'login', userId: this.user()!.id });
      }
    } catch (error) {
      console.error('Keycloak non raggiungibile: l\'app parte senza utente collegato.', error);
    }
  }

  /** Legge da Laravel il profilo dell'utente collegato (al primo accesso lo crea). */
  async loadProfile(): Promise<void> {
    this.user.set(await firstValueFrom(this.http.get<User>(`${environment.apiUrl}/user`)));
  }

  /**
   * Porta alla pagina di accesso di Keycloak, in italiano. Dopo il login l'utente torna all'indirizzo indicato
   * (di norma la pagina che voleva aprire).
   */
  login(redirectPath = '/'): Promise<void> {
    return this.keycloak.login({ redirectUri: window.location.origin + redirectPath, locale: 'it' });
  }

  /** Porta alla pagina di registrazione di Keycloak (con verifica dell'email). */
  register(): Promise<void> {
    return this.keycloak.register({ redirectUri: window.location.origin + '/', locale: 'it' });
  }

  /**
   * Porta a Keycloak per cambiare la password: mostra solo il form "Nuova password", senza la console dell'account.
   * Al ritorno l'utente torna al profilo e l'esito arriva in `actionResult`.
   */
  changePassword(): Promise<void> {
    return this.requestKeycloakAction('UPDATE_PASSWORD');
  }

  /**
   * Porta a Keycloak per modificare nome e cognome (l'email non è modificabile: nel realm è anche il nome utente).
   * Il nuovo nome arriva a Laravel con il token successivo, che lo riallinea al profilo.
   */
  editName(): Promise<void> {
    return this.requestKeycloakAction('UPDATE_PROFILE');
  }

  /** Chiede a Keycloak un'azione mirata sull'utente collegato e poi torna al profilo dell'app. */
  private requestKeycloakAction(action: string): Promise<void> {
    return this.keycloak.login({ action, redirectUri: `${window.location.origin}/profile`, locale: 'it' });
  }

  /** Esce: chiude la sessione su Keycloak e torna alla pagina iniziale. */
  async logout(): Promise<void> {
    this.sessionSync.announce({ type: 'logout' });
    // Una breve pausa prima di lasciare la pagina: Safari può scartare il messaggio se il cambio pagina è immediato
    await new Promise((resolve) => setTimeout(resolve, 150));
    this.user.set(null);
    return this.keycloak.logout({ redirectUri: window.location.origin + '/' });
  }

  /**
   * Reagisce a ciò che comunica un'altra scheda. Una scheda senza utente collegato non ha nulla da correggere.
   *  - Logout: la sessione su Keycloak è chiusa, quindi si butta il token e si torna alla home con un avviso.
   *  - Login di un altro utente: la pagina mostra ancora i dati del vecchio utente; si propone di ricaricare
   *    (non lo si fa da soli, per non far perdere un form compilato). Lo stesso utente non cambia nulla.
   */
  private onOtherTabMessage(message: SessionMessage): void {
    const current = this.user();
    if (!current) {
      return;
    }

    if (message.type === 'logout') {
      this.keycloak.clearToken();
      this.user.set(null);
      this.notifications.info('Sei uscito da un\'altra scheda.');
      void this.router.navigateByUrl('/');
    } else if (message.userId !== current.id) {
      this.notifications.action('In un\'altra scheda è entrato un altro utente.', 'Ricarica', () => window.location.reload());
    }
  }

  /**
   * Il token da mandare a Laravel, rinnovato se scade entro 30 secondi.
   * Restituisce null se l'utente non è collegato; se il rinnovo fallisce (sessione scaduta) riporta al login.
   */
  async getToken(): Promise<string | null> {
    if (!this.keycloak.authenticated) {
      return null;
    }

    try {
      await this.keycloak.updateToken(30);
    } catch {
      await this.login(window.location.pathname);
      return null;
    }

    return this.keycloak.token ?? null;
  }
}
