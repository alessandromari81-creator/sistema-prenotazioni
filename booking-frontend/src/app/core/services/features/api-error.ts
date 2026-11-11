import { HttpErrorResponse } from '@angular/common/http';

/**
 * Estrae un messaggio leggibile da un errore API di Laravel.
 * - 422: usa il primo messaggio di validazione (campo "errors")
 * - altri errori: usa "message"
 * - altrimenti: il testo di riserva passato come secondo parametro
 */
export function apiErrorMessage(e: HttpErrorResponse, fallback: string): string {
  const errors = e.error?.errors;
  if (errors) {
    const first = Object.values(errors)[0] as string[] | undefined;
    if (first?.length) return first[0];
  }
  return e.error?.message ?? fallback;
}