import { TestBed } from '@angular/core/testing';
import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { vi } from 'vitest';
import { AppVersionService } from '../services/features/app-version.service';
import { appVersionInterceptor } from './app-version.interceptor';

describe('appVersionInterceptor', () => {
  let http: HttpClient;
  let controller: HttpTestingController;
  let appVersion: { check: ReturnType<typeof vi.fn> };

  beforeEach(() => {
    appVersion = { check: vi.fn() };
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(withInterceptors([appVersionInterceptor])),
        provideHttpClientTesting(),
        { provide: AppVersionService, useValue: appVersion },
      ],
    });
    http = TestBed.inject(HttpClient);
    controller = TestBed.inject(HttpTestingController);
  });

  it('passa al servizio la versione dichiarata da una risposta dell\'API', () => {
    http.get('/api/courses').subscribe();

    controller.expectOne('/api/courses').flush([], { headers: { 'X-App-Version': 'abc123' } });

    expect(appVersion.check).toHaveBeenCalledWith('abc123');
  });

  it('la legge anche da una risposta d\'errore', () => {
    http.get('/api/courses').subscribe({ error: () => undefined });

    controller.expectOne('/api/courses').flush({}, { status: 422, statusText: 'Unprocessable', headers: { 'X-App-Version': 'abc123' } });

    expect(appVersion.check).toHaveBeenCalledWith('abc123');
  });

  it('passa null se la risposta non ha l\'intestazione', () => {
    http.get('/api/courses').subscribe();

    controller.expectOne('/api/courses').flush([]);

    expect(appVersion.check).toHaveBeenCalledWith(null);
  });

  it('ignora le richieste verso indirizzi diversi dall\'API', () => {
    http.get('https://altro-sito.example/dati').subscribe();

    controller.expectOne('https://altro-sito.example/dati').flush({}, { headers: { 'X-App-Version': 'abc123' } });

    expect(appVersion.check).not.toHaveBeenCalled();
  });
});
