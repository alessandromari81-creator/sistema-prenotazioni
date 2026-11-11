import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/http/auth.service';

// Lascia passare solo gli utenti loggati. Quando il guard parte, init() ha già
// ricostruito l'utente (vedi app.config.ts), quindi isLoggedIn() è affidabile.
export const authGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  const router = inject(Router);
  return auth.isLoggedIn() ? true : router.createUrlTree(['/login']);
};