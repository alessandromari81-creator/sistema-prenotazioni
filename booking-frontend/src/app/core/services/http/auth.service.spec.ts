import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { vi } from 'vitest';
import { AuthService, KEYCLOAK } from './auth.service';

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
    ...overrides,
  };
}

describe('AuthService', () => {
  let http: HttpTestingController;

  function setup(keycloak: ReturnType<typeof fakeKeycloak>): AuthService {
    TestBed.configureTestingModule({
      providers: [
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
});
