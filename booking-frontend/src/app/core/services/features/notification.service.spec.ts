import { TestBed } from '@angular/core/testing';
import { MatSnackBar } from '@angular/material/snack-bar';
import { Subject } from 'rxjs';
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

  it('un avviso con azione resta finché non si sceglie e chiama la funzione quando si preme il pulsante', () => {
    const onAction = new Subject<void>();
    snack.open.mockReturnValue({ onAction: () => onAction });
    const handler = vi.fn();

    service.action('Cambiato.', 'Ricarica', handler);
    onAction.next();

    expect(snack.open).toHaveBeenCalledWith('Cambiato.', 'Ricarica', { duration: 0 });
    expect(handler).toHaveBeenCalledTimes(1);
  });

  it('un\'informazione resta finché non viene chiusa, perché può comparire in una scheda che non si sta guardando', () => {
    service.info('Sei uscito da un\'altra scheda.');

    expect(snack.open).toHaveBeenCalledWith('Sei uscito da un\'altra scheda.', 'Chiudi', { duration: 0 });
  });
});
