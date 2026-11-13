import {
  ApplicationConfig,
  inject,
  LOCALE_ID,
  provideAppInitializer,
  provideBrowserGlobalErrorListeners,
} from '@angular/core';
import { provideRouter, withNavigationErrorHandler } from '@angular/router';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { routes } from './app.routes';
import { appVersionInterceptor } from './core/interceptors/app-version.interceptor';
import { authInterceptor } from './core/interceptors/auth.interceptor';
import { AuthService } from './core/services/http/auth.service';
import { handleNavigationError } from './core/utils/navigation-error-handler';
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
    // Se una pagina non riesce a caricare un pezzo dell'app (scheda vecchia dopo una pubblicazione) si invita ad aggiornare
    provideRouter(routes, withNavigationErrorHandler(handleNavigationError)),
    // authInterceptor aggiunge a ogni richiesta all'API il token di Keycloak;
    // appVersionInterceptor legge la versione pubblicata dalle risposte e avvisa se la pagina è vecchia
    provideHttpClient(withInterceptors([authInterceptor, appVersionInterceptor])),
    // Prima di mostrare qualsiasi pagina, controlla se l'utente ha una sessione su Keycloak e carica il suo profilo
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