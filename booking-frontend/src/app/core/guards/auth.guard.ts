import { inject } from '@angular/core';
import { CanActivateFn } from '@angular/router';
import { AuthService } from '../services/http/auth.service';

/**
 * Lascia passare solo gli utenti collegati. Gli altri vengono mandati alla pagina di accesso di Keycloak
 * e, dopo il login, tornano alla pagina che volevano aprire.
 * Quando la guardia parte, AuthService.init() ha già ricostruito l'utente (vedi app.config.ts).
 */
export const authGuard: CanActivateFn = (_route, state) => {
  const auth = inject(AuthService);

  if (auth.isLoggedIn()) {
    return true;
  }

  void auth.login(state.url);
  return false;
};
