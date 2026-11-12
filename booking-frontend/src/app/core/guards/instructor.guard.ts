import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/http/auth.service';

/**
 * Lascia passare solo chi insegna in almeno un corso (informazione che Laravel restituisce in GET /api/user).
 * Chi non è collegato va al login, chi non è istruttore torna all'elenco dei corsi.
 */
export const instructorGuard: CanActivateFn = (_route, state) => {
  const auth = inject(AuthService);
  const router = inject(Router);

  if (!auth.isLoggedIn()) {
    void auth.login(state.url);
    return false;
  }

  return auth.teachesCourses() ? true : router.createUrlTree(['/courses']);
};
