import { HttpErrorResponse, HttpInterceptorFn, HttpResponse } from '@angular/common/http';
import { inject } from '@angular/core';
import { tap } from 'rxjs';
import { environment } from '../../../environments/environment';
import { AppVersionService } from '../services/features/app-version.service';

/**
 * Legge l'intestazione "X-App-Version" delle risposte dell'API (anche quelle d'errore) e la fa confrontare
 * con la versione dell'app: così, senza richieste in più, si scopre che è uscita una versione nuova.
 * Guarda solo le chiamate verso l'API.
 */
export const appVersionInterceptor: HttpInterceptorFn = (req, next) => {
  if (!req.url.startsWith(environment.apiUrl)) {
    return next(req);
  }

  const appVersion = inject(AppVersionService);

  return next(req).pipe(
    tap({
      next: (event) => {
        if (event instanceof HttpResponse) {
          appVersion.check(event.headers.get('X-App-Version'));
        }
      },
      error: (error: unknown) => {
        if (error instanceof HttpErrorResponse) {
          appVersion.check(error.headers.get('X-App-Version'));
        }
      },
    })
  );
};
