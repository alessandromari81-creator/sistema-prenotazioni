import {
  ApplicationConfig,
  inject,
  LOCALE_ID,
  provideAppInitializer,
  provideBrowserGlobalErrorListeners,
} from '@angular/core';
import { provideRouter } from '@angular/router';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { routes } from './app.routes';
import { authInterceptor } from './core/interceptors/auth.interceptor';
import { AuthService } from './core/services/http/auth.service';
import { MAT_DATE_LOCALE, provideNativeDateAdapter } from '@angular/material/core';
import { registerLocaleData } from '@angular/common';
import localeIt from '@angular/common/locales/it';
import { provideLuxonDateAdapter } from '@angular/material-luxon-adapter';

// Registra i dati della lingua italiana: senza questa riga la pipe `date`
// va in errore (NG0701: Missing locale data).
registerLocaleData(localeIt);

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideRouter(routes),
    // La gestione del cookie XSRF è inclusa di default in HttpClient
    provideHttpClient(withInterceptors([authInterceptor])),
    // Prima di mostrare qualsiasi pagina, chiede al server se la sessione è valida
    provideAppInitializer(() => inject(AuthService).init()),
    { provide: LOCALE_ID, useValue: 'it-IT' },         // lingua per le pipe (date, number)
    { provide: MAT_DATE_LOCALE, useValue: 'it-IT' },   // lingua del calendario Material
    provideLuxonDateAdapter({
      parse: {
        dateInput: 'dd/MM/yyyy'
      },
      display: {
        dateInput: 'dd/MM/yyyy',
        monthYearLabel: 'MMMM yyyy',
        dateA11yLabel: 'dd/MM/yyyy',
        monthYearA11yLabel: 'MMMM yyyy'
      }
    })
  ],
};