import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/http/auth.service';

/**
 * Lascia passare solo gli amministratori. Chi non è collegato va al login, chi è collegato ma non admin torna
 * all'elenco dei corsi.
 * Questa guardia migliora solo l'esperienza d'uso: la vera protezione resta nel backend (Gate 'admin'),
 * che risponde 403 anche se qualcuno aggira il frontend.
 */
export const adminGuard: CanActivateFn = (_route, state) => {
  const auth = inject(AuthService);
  const router = inject(Router);

  if (!auth.isLoggedIn()) {
    void auth.login(state.url);
    return false;
  }

  return auth.isAdmin() ? true : router.createUrlTree(['/courses']);
};
