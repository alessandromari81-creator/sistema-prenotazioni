import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, throwError } from 'rxjs';
import { AuthService } from '../services/http/auth.service';

// Interceptor funzionale: niente più Bearer token.
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);

  // Accept: application/json fa rispondere Laravel con errori JSON (401, 422...)
  // invece di redirect HTML. X-Requested-With segnala una richiesta AJAX.
  const cloned = req.clone({
    setHeaders: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return next(cloned).pipe(
    catchError((err: HttpErrorResponse) => {
      // 401 mentre ero loggato = sessione scaduta: pulisco lo stato e vado al login
      if (err.status === 401 && auth.isLoggedIn()) {
        auth.clear();
      }
      return throwError(() => err);
    })
  );
};