import { of } from 'rxjs';
import { vi } from 'vitest';

/**
 * Un finto `ConfirmService` per i test: `answer` decide cosa "risponde l'utente" alla finestra di conferma
 * (true = conferma, false = rinuncia) e `confirm` è una funzione spia con cui controllare cosa è stato chiesto.
 * Si registra nei provider: `{ provide: ConfirmService, useValue: confirmer }`.
 */
export class FakeConfirm {
  answer = true;
  confirm = vi.fn(() => of(this.answer));
}
