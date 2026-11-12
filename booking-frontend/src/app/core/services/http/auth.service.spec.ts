import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { vi } from 'vitest';
import { Router } from '@angular/router';
import { AuthService, KEYCLOAK } from './auth.service';
import { NotificationService } from '../features/notification.service';
import { SessionMessage, SessionSyncService } from '../features/session-sync.service';

/** Un Keycloak finto: si controlla cosa gli chiede l'app, senza un server vero. */
function fakeKeycloak(overrides: Record<string, unknown> = {}) {
  return {
    authenticated: false,
    token: 'token-di-prova',
    init: vi.fn().mockResolvedValue(false),
    login: vi.fn().mockResolvedValue(undefined),
    register: vi.fn().mockResolvedValue(undefined),
    logout: vi.fn().mockResolvedValue(undefined),
    updateToken: vi.fn().mockResolvedValue(true),
    clearToken: vi.fn(),
    ...overrides,
  };
}

describe('AuthService', () => {
  let http: HttpTestingController;
  // Il canale tra le schede è finto: si prende il "gestore" registrato da AuthService per simulare i messaggi altrui
  let sync: { announce: ReturnType<typeof vi.fn>; listen: ReturnType<typeof vi.fn> };
  let notifications: { info: ReturnType<typeof vi.fn>; action: ReturnType<typeof vi.fn> };
  let navigate: ReturnType<typeof vi.fn>;

  /** Simula un messaggio arrivato da un'altra scheda. */
  function fromOtherTab(message: SessionMessage): void {
    sync.listen.mock.calls[0][0](message);
  }

  function setup(keycloak: ReturnType<typeof fakeKeycloak>): AuthService {
    sync = { announce: vi.fn(), listen: vi.fn() };
    notifications = { info: vi.fn(), action: vi.fn() };
    navigate = vi.fn().mockResolvedValue(true);
    TestBed.configureTestingModule({
      providers: [
        { provide: SessionSyncService, useValue: sync },
        { provide: NotificationService, useValue: notifications },
        { provide: Router, useValue: { navigateByUrl: navigate } },
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: KEYCLOAK, useValue: keycloak },
      ],
    });
    http = TestBed.inject(HttpTestingController);
    return TestBed.inject(AuthService);
  }

  it('all\'avvio senza sessione l\'utente resta anonimo e non chiama l\'API', async () => {
    const service = setup(fakeKeycloak());

    await service.init();

    expect(service.isLoggedIn()).toBe(false);
    http.expectNone('/api/user');
  });

  it('all\'avvio con sessione carica il profilo da Laravel', async () => {
    const service = setup(fakeKeycloak({ init: vi.fn().mockResolvedValue(true), authenticated: true }));

    const started = service.init();
    await Promise.resolve();
    await Promise.resolve();
    http.expectOne('/api/user').flush({ id: 1, name: 'Mario', email: 'm@example.com', role: 'user', teaches_courses: true });
    await started;

    expect(service.isLoggedIn()).toBe(true);
    expect(service.user()?.name).toBe('Mario');
    expect(service.teachesCourses()).toBe(true);
    expect(service.isAdmin()).toBe(false);
  });

  it('riconosce un amministratore dal ruolo del profilo', async () => {
    const service = setup(fakeKeycloak({ init: vi.fn().mockResolvedValue(true), authenticated: true }));

    const started = service.init();
    await Promise.resolve();
    await Promise.resolve();
    http.expectOne('/api/user').flush({ id: 1, name: 'Admin', email: 'a@example.com', role: 'admin' });
    await started;

    expect(service.isAdmin()).toBe(true);
    expect(service.teachesCourses()).toBe(false);
  });

  it('se Keycloak non risponde l\'app parte comunque, senza utente', async () => {
    const spy = vi.spyOn(console, 'error').mockImplementation(() => undefined);
    const service = setup(fakeKeycloak({ init: vi.fn().mockRejectedValue(new Error('rete')) }));

    await expect(service.init()).resolves.toBeUndefined();

    expect(service.isLoggedIn()).toBe(false);
    spy.mockRestore();
  });

  it('il login porta a Keycloak (in italiano) e dopo torna all\'indirizzo richiesto', async () => {
    const keycloak = fakeKeycloak();
    const service = setup(keycloak);

    await service.login('/courses');

    expect(keycloak.login).toHaveBeenCalledWith({ redirectUri: `${window.location.origin}/courses`, locale: 'it' });
  });

  it('la registrazione porta alla pagina di registrazione di Keycloak', async () => {
    const keycloak = fakeKeycloak();
    const service = setup(keycloak);

    await service.register();

    expect(keycloak.register).toHaveBeenCalledWith({ redirectUri: `${window.location.origin}/`, locale: 'it' });
  });

  it('cambiare la password chiede a Keycloak solo l\'azione "nuova password" e poi torna al profilo', async () => {
    const keycloak = fakeKeycloak();
    const service = setup(keycloak);

    await service.changePassword();

    expect(keycloak.login).toHaveBeenCalledWith({ action: 'UPDATE_PASSWORD', redirectUri: `${window.location.origin}/profile`, locale: 'it' });
  });

  it('modificare il nome chiede a Keycloak solo l\'azione "aggiorna profilo" e poi torna al profilo', async () => {
    const keycloak = fakeKeycloak();
    const service = setup(keycloak);

    await service.editName();

    expect(keycloak.login).toHaveBeenCalledWith({ action: 'UPDATE_PROFILE', redirectUri: `${window.location.origin}/profile`, locale: 'it' });
  });

  it('non c\'è nessun esito finché non si torna da un\'azione di Keycloak', async () => {
    const service = setup(fakeKeycloak());

    await service.init();

    expect(service.actionResult()).toBeNull();
  });

  it('quando si torna da un\'azione di Keycloak ne registra l\'esito', async () => {
    const keycloak = fakeKeycloak();
    const service = setup(keycloak);
    await service.init();

    // keycloak-js chiama questa funzione quando l'indirizzo di ritorno contiene l'esito dell'azione
    (keycloak as unknown as { onActionUpdate: (status: string, action?: string) => void }).onActionUpdate('success', 'UPDATE_PASSWORD');

    expect(service.actionResult()).toEqual({ status: 'success', action: 'UPDATE_PASSWORD' });
  });

  it('il logout azzera l\'utente e chiude la sessione su Keycloak', async () => {
    const keycloak = fakeKeycloak();
    const service = setup(keycloak);
    service.user.set({ id: 1, name: 'Mario', email: 'm@example.com', role: 'user' });

    await service.logout();

    expect(service.isLoggedIn()).toBe(false);
    expect(keycloak.logout).toHaveBeenCalledWith({ redirectUri: `${window.location.origin}/` });
  });

  it('senza utente collegato non ha un token da mandare', async () => {
    const keycloak = fakeKeycloak();
    const service = setup(keycloak);

    expect(await service.getToken()).toBeNull();
    expect(keycloak.updateToken).not.toHaveBeenCalled();
  });

  it('rinnova il token se scade entro 30 secondi e lo restituisce', async () => {
    const keycloak = fakeKeycloak({ authenticated: true });
    const service = setup(keycloak);

    expect(await service.getToken()).toBe('token-di-prova');
    expect(keycloak.updateToken).toHaveBeenCalledWith(30);
  });

  it('se il rinnovo fallisce (sessione scaduta) riporta al login', async () => {
    const keycloak = fakeKeycloak({ authenticated: true, updateToken: vi.fn().mockRejectedValue(new Error('scaduta')) });
    const service = setup(keycloak);

    expect(await service.getToken()).toBeNull();
    expect(keycloak.login).toHaveBeenCalled();
  });

  describe('sincronizzazione tra schede', () => {
    /** Avvia il servizio con un utente collegato (id 7) e lascia che i suoi messaggi arrivino al canale finto. */
    async function startLoggedIn(keycloak = fakeKeycloak({ init: vi.fn().mockResolvedValue(true), authenticated: true })) {
      const service = setup(keycloak);
      const started = service.init();
      await Promise.resolve();
      await Promise.resolve();
      http.expectOne('/api/user').flush({ id: 7, name: 'Mario', email: 'm@example.com', role: 'user' });
      await started;
      return { service, keycloak };
    }

    it('dopo l\'accesso avvisa le altre schede con l\'id dell\'utente, mai con il token', async () => {
      await startLoggedIn();

      expect(sync.announce).toHaveBeenCalledWith({ type: 'login', userId: 7 });
    });

    it('senza sessione non annuncia nulla', async () => {
      const service = setup(fakeKeycloak());

      await service.init();

      expect(sync.announce).not.toHaveBeenCalled();
    });

    it('il logout avvisa le altre schede', async () => {
      const { service } = await startLoggedIn();

      await service.logout();

      expect(sync.announce).toHaveBeenCalledWith({ type: 'logout' });
    });

    it('se un\'altra scheda esce, questa butta il token, torna alla home e avvisa', async () => {
      const { service, keycloak } = await startLoggedIn();

      fromOtherTab({ type: 'logout' });

      expect(keycloak.clearToken).toHaveBeenCalled();
      expect(service.isLoggedIn()).toBe(false);
      expect(navigate).toHaveBeenCalledWith('/');
      expect(notifications.info).toHaveBeenCalledWith('Sei uscito da un\'altra scheda.');
    });

    it('se in un\'altra scheda entra un altro utente propone di ricaricare, senza ricaricare da sola', async () => {
      const { service } = await startLoggedIn();

      fromOtherTab({ type: 'login', userId: 99 });

      expect(notifications.action).toHaveBeenCalledWith(expect.any(String), 'Ricarica', expect.any(Function));
      expect(service.user()?.id).toBe(7);
    });

    it('se in un\'altra scheda entra lo stesso utente non fa nulla', async () => {
      await startLoggedIn();

      fromOtherTab({ type: 'login', userId: 7 });

      expect(notifications.action).not.toHaveBeenCalled();
      expect(notifications.info).not.toHaveBeenCalled();
    });

    it('una scheda senza utente ignora i messaggi delle altre', async () => {
      const service = setup(fakeKeycloak());
      await service.init();

      fromOtherTab({ type: 'logout' });
      fromOtherTab({ type: 'login', userId: 1 });

      expect(navigate).not.toHaveBeenCalled();
      expect(notifications.action).not.toHaveBeenCalled();
    });
  });
});
