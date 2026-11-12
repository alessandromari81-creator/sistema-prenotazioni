import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, from, switchMap, throwError } from 'rxjs';
import { environment } from '../../../environments/environment';
import { AuthService } from '../services/http/auth.service';

/**
 * Aggiunge a ogni richiesta verso l'API il token di Keycloak (header "Authorization: Bearer ...").
 * Non tocca le richieste verso altri indirizzi, così il token non viene mai inviato a terzi.
 *
 * Se l'API risponde 401 mentre l'utente risulta collegato, il token non è più valido: si torna al login.
 */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  if (!req.url.startsWith(environment.apiUrl)) {
    return next(req);
  }

  const auth = inject(AuthService);

  return from(auth.getToken()).pipe(
    switchMap((token) =>
      next(
        req.clone({
          // Accept: application/json fa rispondere Laravel con errori JSON (401, 422...) invece di redirect HTML
          setHeaders: token
            ? { Accept: 'application/json', Authorization: `Bearer ${token}` }
            : { Accept: 'application/json' },
        })
      )
    ),
    catchError((err: HttpErrorResponse) => {
      if (err.status === 401 && auth.isLoggedIn()) {
        void auth.login(window.location.pathname);
      }
      return throwError(() => err);
    })
  );
};
