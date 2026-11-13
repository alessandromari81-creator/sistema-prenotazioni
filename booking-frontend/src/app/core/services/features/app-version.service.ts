import { Injectable, inject } from '@angular/core';
import { APP_VERSION } from '../../../../environments/version';
import { NotificationService } from './notification.service';

/**
 * Si accorge che la pagina aperta è vecchia e invita l'utente ad aggiornarla.
 *
 * Il backend scrive la versione pubblicata nell'intestazione "X-App-Version" di ogni risposta; l'app conosce la
 * versione con cui è stata costruita (`APP_VERSION`). Se sono diverse, dopo la costruzione è stata pubblicata una
 * versione nuova. L'avviso non ricarica da solo la pagina, per non far perdere un form compilato a metà.
 */
@Injectable({ providedIn: 'root' })
export class AppVersionService {
  private notifications = inject(NotificationService);

  // L'avviso si mostra una volta sola: le chiamate successive porterebbero la stessa versione diversa
  private notified = false;

  /**
   * Confronta la versione dichiarata dal server con quella dell'app e, se differiscono, mostra l'avviso "Aggiorna".
   * Se il server non dichiara nessuna versione (o la app è costruita senza versione) non fa nulla.
   */
  check(serverVersion: string | null): void {
    if (!serverVersion || serverVersion === APP_VERSION) {
      return;
    }

    this.notifyOutdated();
  }

  /**
   * Mostra l'avviso "nuova versione" (una volta sola). Si usa anche quando una pagina non riesce a caricare un pezzo
   * dell'app (lazy loading) perché dopo una pubblicazione quel file non esiste più: segno sicuro che la pagina è vecchia.
   */
  notifyOutdated(): void {
    if (this.notified) {
      return;
    }

    this.notified = true;
    this.notifications.action('È disponibile una nuova versione dell\'app.', 'Aggiorna', () => window.location.reload());
  }
}
