import { Injectable, InjectionToken, computed, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import Keycloak from 'keycloak-js';
import { environment } from '../../../../environments/environment';
import { User } from '../../models/models';

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

  // L'unico stato è il profilo dell'utente: il token lo tiene keycloak-js in memoria
  readonly user = signal<User | null>(null);
  readonly isLoggedIn = computed(() => this.user() !== null);
  readonly isAdmin = computed(() => this.user()?.role === 'admin');
  readonly teachesCourses = computed(() => this.user()?.teaches_courses === true);

  /**
   * Eseguita all'avvio dell'app, prima di mostrare qualsiasi pagina. Controlla se l'utente ha già una sessione
   * su Keycloak (o se sta tornando dalla pagina di accesso) e, se sì, carica il suo profilo.
   * Se Keycloak non risponde l'app parte comunque, come se l'utente non fosse collegato.
   */
  async init(): Promise<void> {
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
   * Apre la pagina di gestione dell'account di Keycloak: da lì si cambiano nome, email e password.
   * Sono dati di Keycloak, non dell'app, quindi non si modificano nel profilo dell'applicazione.
   */
  manageAccount(): Promise<void> {
    return this.keycloak.accountManagement();
  }

  /** Esce: chiude la sessione su Keycloak e torna alla pagina iniziale. */
  logout(): Promise<void> {
    this.user.set(null);
    return this.keycloak.logout({ redirectUri: window.location.origin + '/' });
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
