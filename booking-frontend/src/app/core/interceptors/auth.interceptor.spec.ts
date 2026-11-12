import { TestBed } from '@angular/core/testing';
import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { vi } from 'vitest';
import { AuthService } from '../services/http/auth.service';
import { authInterceptor } from './auth.interceptor';

describe('authInterceptor', () => {
  let http: HttpClient;
  let controller: HttpTestingController;
  let auth: { getToken: ReturnType<typeof vi.fn>; isLoggedIn: ReturnType<typeof vi.fn>; login: ReturnType<typeof vi.fn> };

  beforeEach(() => {
    auth = {
      getToken: vi.fn().mockResolvedValue('il-mio-token'),
      isLoggedIn: vi.fn().mockReturnValue(true),
      login: vi.fn().mockResolvedValue(undefined),
    };

    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(withInterceptors([authInterceptor])),
        provideHttpClientTesting(),
        { provide: AuthService, useValue: auth },
      ],
    });

    http = TestBed.inject(HttpClient);
    controller = TestBed.inject(HttpTestingController);
  });

  /** Aspetta che l'interceptor abbia ottenuto il token (è asincrono) e restituisce la richiesta intercettata. */
  async function expectRequest(url: string) {
    await vi.waitFor(() => expect(controller.match(url).length).toBeGreaterThanOrEqual(0));
    return vi.waitFor(() => controller.expectOne(url));
  }

  it('aggiunge il token Bearer alle richieste verso l\'API', async () => {
    http.get('/api/courses').subscribe();

    const req = await expectRequest('/api/courses');

    expect(req.request.headers.get('Authorization')).toBe('Bearer il-mio-token');
    expect(req.request.headers.get('Accept')).toBe('application/json');
    req.flush([]);
  });

  it('non manda il token a indirizzi diversi dall\'API', async () => {
    http.get('https://altro-sito.example/dati').subscribe();

    const req = controller.expectOne('https://altro-sito.example/dati');

    expect(req.request.headers.has('Authorization')).toBe(false);
    expect(auth.getToken).not.toHaveBeenCalled();
    req.flush({});
  });

  it('se non c\'è un token manda comunque la richiesta, senza Authorization', async () => {
    auth.getToken.mockResolvedValue(null);

    http.get('/api/courses').subscribe({ error: () => undefined });
    const req = await expectRequest('/api/courses');

    expect(req.request.headers.has('Authorization')).toBe(false);
    expect(req.request.headers.get('Accept')).toBe('application/json');
    req.flush([]);
  });

  it('con un 401 mentre l\'utente è collegato torna al login', async () => {
    http.get('/api/courses').subscribe({ error: () => undefined });

    const req = await expectRequest('/api/courses');
    req.flush({ message: 'Non autenticato.' }, { status: 401, statusText: 'Unauthorized' });

    await vi.waitFor(() => expect(auth.login).toHaveBeenCalled());
  });

  it('un 401 senza utente collegato non provoca un nuovo login', async () => {
    auth.isLoggedIn.mockReturnValue(false);
    http.get('/api/courses').subscribe({ error: () => undefined });

    const req = await expectRequest('/api/courses');
    req.flush({}, { status: 401, statusText: 'Unauthorized' });

    await Promise.resolve();
    expect(auth.login).not.toHaveBeenCalled();
  });

  it('un errore diverso (403) non fa tornare al login', async () => {
    http.get('/api/courses').subscribe({ error: () => undefined });

    const req = await expectRequest('/api/courses');
    req.flush({}, { status: 403, statusText: 'Forbidden' });

    await Promise.resolve();
    expect(auth.login).not.toHaveBeenCalled();
  });
});
