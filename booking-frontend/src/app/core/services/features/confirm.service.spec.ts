import { TestBed } from '@angular/core/testing';
import { MatDialog } from '@angular/material/dialog';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { ConfirmDialogComponent } from '../../../shared/confirm-dialog/confirm-dialog.component';
import { ConfirmService } from './confirm.service';

describe('ConfirmService', () => {
  const data = { title: 'Eliminare?', message: 'Non si può disfare.', confirmLabel: 'Elimina' };

  function setup(closedWith: unknown) {
    const dialog = { open: vi.fn().mockReturnValue({ afterClosed: () => of(closedWith) }) };
    TestBed.configureTestingModule({ providers: [{ provide: MatDialog, useValue: dialog }] });
    return { dialog, service: TestBed.inject(ConfirmService) };
  }

  it('apre la finestra di conferma con i testi indicati', () => {
    const { dialog, service } = setup(true);

    service.confirm(data).subscribe();

    expect(dialog.open).toHaveBeenCalledWith(ConfirmDialogComponent, expect.objectContaining({ data }));
  });

  it('emette true se l\'utente conferma', () => {
    const { service } = setup(true);
    const answer = vi.fn();

    service.confirm(data).subscribe(answer);

    expect(answer).toHaveBeenCalledWith(true);
  });

  it('emette false se l\'utente rinuncia', () => {
    const { service } = setup(false);
    const answer = vi.fn();

    service.confirm(data).subscribe(answer);

    expect(answer).toHaveBeenCalledWith(false);
  });

  it('emette false se la finestra si chiude senza una scelta (Esc o clic fuori)', () => {
    const { service } = setup(undefined);
    const answer = vi.fn();

    service.confirm(data).subscribe(answer);

    expect(answer).toHaveBeenCalledWith(false);
  });
});
