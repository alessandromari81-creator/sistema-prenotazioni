import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/http/auth.service';

// Lascia passare solo gli admin. Chi non è loggato va al login,
// chi è loggato ma non admin torna alla lista delle risorse.
// Attenzione: questo guard migliora solo l'esperienza d'uso; la vera protezione
// resta nel backend (Gate 'admin'), che risponde 403 anche se qualcuno aggira il frontend.
export const adminGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  const router = inject(Router);

  if (!auth.isLoggedIn()) return router.createUrlTree(['/login']);
  return auth.isAdmin() ? true : router.createUrlTree(['/bookables']);
};