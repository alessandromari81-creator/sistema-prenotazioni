import { inject } from '@angular/core';
import { NavigationError } from '@angular/router';
import { AppVersionService } from '../services/features/app-version.service';
import { isChunkLoadError } from './chunk-error';

/**
 * Cosa fare quando una navigazione fallisce. Se la causa è un pezzo dell'app che non si scarica (pagina vecchia dopo una
 * pubblicazione) si invita l'utente ad aggiornare, invece di lasciarlo davanti a una pagina che non reagisce.
 * Ogni altro errore viene rilanciato, come farebbe Angular senza questo gestore, così non si nasconde nulla.
 */
export function handleNavigationError(navigationError: NavigationError): never | void {
  if (isChunkLoadError(navigationError.error)) {
    inject(AppVersionService).notifyOutdated();
    return;
  }

  throw navigationError.error;
}
