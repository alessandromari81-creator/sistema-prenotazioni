import { Injectable, OnDestroy } from '@angular/core';

/**
 * Cosa una scheda comunica alle altre: "è entrato l'utente con questo id" oppure "è uscito".
 * Non c'è mai il token: le altre schede lo chiedono da sole a Keycloak, quindi resta solo nella memoria di ciascuna.
 */
export type SessionMessage = { type: 'login'; userId: number } | { type: 'logout' };

/**
 * Tiene allineate le schede del browser aperte sullo stesso sito.
 *
 * Ogni scheda ha il suo token in memoria e Keycloak non avvisa le altre quando una fa login o logout
 * (nell'app `checkLoginIframe` è spento). Con un BroadcastChannel (un "canale radio" tra le schede dello stesso sito)
 * la scheda che cambia stato lo annuncia, e le altre decidono cosa fare.
 * Una scheda non riceve i propri messaggi.
 */
@Injectable({ providedIn: 'root' })
export class SessionSyncService implements OnDestroy {
  // I browser molto vecchi non hanno BroadcastChannel: in quel caso la sincronizzazione semplicemente non c'è
  private channel = typeof BroadcastChannel === 'undefined' ? null : new BroadcastChannel('palestra-auth');

  /** Avvisa le altre schede. */
  announce(message: SessionMessage): void {
    this.channel?.postMessage(message);
  }

  /** Chiama `handler` ogni volta che un'altra scheda manda un messaggio valido; ignora tutto il resto. */
  listen(handler: (message: SessionMessage) => void): void {
    if (!this.channel) {
      return;
    }

    this.channel.onmessage = (event: MessageEvent) => {
      const data = event.data;
      if (data?.type === 'logout' || (data?.type === 'login' && typeof data.userId === 'number')) {
        handler(data as SessionMessage);
      }
    };
  }

  /** Chiude il canale quando il servizio viene distrutto (utile nei test). */
  ngOnDestroy(): void {
    this.channel?.close();
  }
}
