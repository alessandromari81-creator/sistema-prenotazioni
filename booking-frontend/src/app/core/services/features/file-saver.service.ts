import { Injectable } from '@angular/core';

/**
 * Consegna al browser un file che l'app ha già in memoria (un Blob), che l'utente salva con il nome indicato.
 * Serve quando il file è dietro l'autenticazione: un normale link non manda il token, quindi lo si scarica con HttpClient
 * (che lo manda) e poi lo si "consegna" da un indirizzo temporaneo, subito liberato.
 * È un servizio (e non una funzione) perché nei test si sostituisce con uno finto: jsdom non scarica file.
 */
@Injectable({ providedIn: 'root' })
export class FileSaverService {
  /**
   * Mostra un PDF nel visualizzatore del browser, in una scheda nuova. La scheda va aperta SUBITO al clic (con
   * window.open nel gestore del clic) e passata qui quando il file è arrivato: aprirla dopo l'attesa della rete farebbe
   * credere al browser che sia un popup indesiderato e la bloccherebbe. Se la scheda è stata bloccata ($tab null) il file
   * si scarica con il nome indicato.
   */
  show(blob: Blob, tab: Window | null, filename: string): void {
    if (!tab) {
      this.save(blob, filename);
      return;
    }

    const url = URL.createObjectURL(new Blob([blob], { type: 'application/pdf' }));
    tab.location.href = url;

    // L'indirizzo serve alla scheda finché è aperta: lo si libera dopo un po', non subito
    setTimeout(() => URL.revokeObjectURL(url), 60_000);
  }

  /** Fa scaricare $blob al browser con il nome $filename. */
  save(blob: Blob, filename: string): void {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();

    URL.revokeObjectURL(url);
  }
}
