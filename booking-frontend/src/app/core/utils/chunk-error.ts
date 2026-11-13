/**
 * Dice se un errore viene dal caricamento di un pezzo dell'app (lazy loading) che non si riesce a scaricare.
 * Succede tipicamente a chi ha una pagina vecchia aperta dopo una nuova pubblicazione: quel file non esiste più
 * (o il server risponde con una pagina HTML). Ogni browser lo scrive a modo suo, quindi si riconoscono i messaggi noti.
 */
export function isChunkLoadError(error: unknown): boolean {
  const message = error instanceof Error ? error.message : typeof error === 'string' ? error : '';

  return /dynamically imported module|importing a module script failed|not a valid javascript mime type|loading chunk .* failed|chunkloaderror/i.test(
    message
  );
}
