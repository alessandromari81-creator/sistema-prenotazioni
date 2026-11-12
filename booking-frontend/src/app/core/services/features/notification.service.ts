import { Injectable, inject } from '@angular/core';
import { MatSnackBar } from '@angular/material/snack-bar';

/**
 * Gli avvisi a comparsa dell'app (snackbar), con regole uguali ovunque:
 *  - un esito positivo sparisce da solo dopo 5 secondi (e si può chiudere prima con "Chiudi");
 *  - un errore resta finché l'utente non lo chiude, perché un errore che sparisce da solo si rischia di non fare in tempo
 *    a leggerlo (WCAG 2.2.1), ed è annunciato con priorità alta ai lettori di schermo.
 *
 * Servono per i risultati delle azioni (salvare, iscriversi, eliminare). Gli errori di caricamento di una pagina restano
 * invece scritti nella pagina, perché ne descrivono lo stato: sparendo, farebbero credere che non ci sia nulla da mostrare.
 */
@Injectable({ providedIn: 'root' })
export class NotificationService {
  private snackBar = inject(MatSnackBar);

  /** Mostra un esito positivo: sparisce dopo 5 secondi. */
  success(message: string): void {
    this.snackBar.open(message, 'Chiudi', { duration: 5000 });
  }

  /** Mostra un errore: resta finché non viene chiuso e viene annunciato con priorità alta. */
  error(message: string): void {
    this.snackBar.open(message, 'Chiudi', { duration: 0, politeness: 'assertive' });
  }
}
