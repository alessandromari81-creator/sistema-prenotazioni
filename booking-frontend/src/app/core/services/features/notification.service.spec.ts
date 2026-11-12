import { TestBed } from '@angular/core/testing';
import { MatSnackBar } from '@angular/material/snack-bar';
import { vi } from 'vitest';
import { NotificationService } from './notification.service';

describe('NotificationService', () => {
  let snack: { open: ReturnType<typeof vi.fn> };
  let service: NotificationService;

  beforeEach(() => {
    snack = { open: vi.fn() };
    TestBed.configureTestingModule({ providers: [{ provide: MatSnackBar, useValue: snack }] });
    service = TestBed.inject(NotificationService);
  });

  it('un esito positivo sparisce da solo dopo 5 secondi e ha il pulsante "Chiudi"', () => {
    service.success('Fatto.');

    expect(snack.open).toHaveBeenCalledWith('Fatto.', 'Chiudi', { duration: 5000 });
  });

  it('un errore resta finché non viene chiuso (durata 0)', () => {
    service.error('Qualcosa è andato storto.');

    const options = snack.open.mock.calls[0][2];
    expect(options.duration).toBe(0);
  });

  it('un errore è annunciato ai lettori di schermo con priorità alta', () => {
    service.error('Qualcosa è andato storto.');

    expect(snack.open).toHaveBeenCalledWith('Qualcosa è andato storto.', 'Chiudi', { duration: 0, politeness: 'assertive' });
  });

  it('un esito positivo non è "assertivo": non interrompe chi sta leggendo', () => {
    service.success('Fatto.');

    expect(snack.open.mock.calls[0][2].politeness).toBeUndefined();
  });
});
