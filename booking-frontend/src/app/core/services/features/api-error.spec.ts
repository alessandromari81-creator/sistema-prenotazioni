import { HttpErrorResponse } from '@angular/common/http';
import { apiErrorMessage } from './api-error';

describe('apiErrorMessage', () => {
  it('usa il primo messaggio di validazione di un errore 422', () => {
    const e = new HttpErrorResponse({ status: 422, error: { message: 'x', errors: { course: ['Il corso è al completo.'] } } });

    expect(apiErrorMessage(e, 'riserva')).toBe('Il corso è al completo.');
  });

  it('usa "message" quando non ci sono errori di campo', () => {
    const e = new HttpErrorResponse({ status: 403, error: { message: 'Non autorizzato.' } });

    expect(apiErrorMessage(e, 'riserva')).toBe('Non autorizzato.');
  });

  it('usa il testo di riserva se la risposta non ha un messaggio', () => {
    const e = new HttpErrorResponse({ status: 500, error: null });

    expect(apiErrorMessage(e, 'Qualcosa è andato storto')).toBe('Qualcosa è andato storto');
  });
});
