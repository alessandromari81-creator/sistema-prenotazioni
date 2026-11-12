import { Injectable } from '@angular/core';

/**
 * Consegna al browser un file che l'app ha già in memoria (un Blob), che l'utente salva con il nome indicato.
 * Serve quando il file è dietro l'autenticazione: un normale link non manda il token, quindi lo si scarica con HttpClient
 * (che lo manda) e poi lo si "consegna" da un indirizzo temporaneo, subito liberato.
 * È un servizio (e non una funzione) perché nei test si sostituisce con uno finto: jsdom non scarica file.
 */
@Injectable({ providedIn: 'root' })
export class FileSaverService {
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
