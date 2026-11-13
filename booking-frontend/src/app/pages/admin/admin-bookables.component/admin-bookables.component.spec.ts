import { TestBed } from '@angular/core/testing';
import { HttpErrorResponse } from '@angular/common/http';
import { provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { FakeConfirm } from '../../../../testing/confirm';
import { Bookable } from '../../../core/models/models';
import { ConfirmService } from '../../../core/services/features/confirm.service';
import { BookingService } from '../../../core/services/http/booking.service';
import { AdminBookablesComponent } from './admin-bookables.component';

const sala: Bookable = { id: 3, name: 'Sala pesi', slot_minutes: 60, is_active: true };

describe('AdminBookablesComponent: eliminare una sala', () => {
  const confirmer = new FakeConfirm();
  let api: { getBookables: ReturnType<typeof vi.fn>; deleteBookable: ReturnType<typeof vi.fn> };

  async function render() {
    confirmer.confirm.mockClear();
    api = {
      getBookables: vi.fn().mockReturnValue(of({ data: [sala], total: 1 })),
      deleteBookable: vi.fn().mockReturnValue(of(undefined)),
    };

    await TestBed.configureTestingModule({
      imports: [AdminBookablesComponent],
      providers: [provideRouter([]), { provide: BookingService, useValue: api }, { provide: ConfirmService, useValue: confirmer }],
    }).compileComponents();

    const fixture = TestBed.createComponent(AdminBookablesComponent);
    fixture.detectChanges();
    return fixture.componentInstance;
  }

  it('chiede conferma avvisando che si perdono orari e prenotazioni, poi elimina e ricarica l\'elenco', async () => {
    confirmer.answer = true;
    const component = await render();

    component.remove(sala);

    expect(confirmer.confirm).toHaveBeenCalledWith(expect.objectContaining({
      confirmLabel: 'Elimina sala',
      message: expect.stringContaining('tutte le sue prenotazioni'),
    }));
    expect(api.deleteBookable).toHaveBeenCalledWith(3);
    expect(api.getBookables).toHaveBeenCalledTimes(2);
  });

  it('se l\'utente rinuncia non elimina nulla', async () => {
    confirmer.answer = false;
    const component = await render();

    component.remove(sala);

    expect(api.deleteBookable).not.toHaveBeenCalled();
    expect(api.getBookables).toHaveBeenCalledTimes(1);
  });

  it('se il server rifiuta mostra il motivo nella pagina', async () => {
    confirmer.answer = true;
    const component = await render();
    api.deleteBookable.mockReturnValue(throwError(() => new HttpErrorResponse({ status: 409, error: { message: 'La sala ha prenotazioni future.' } })));

    component.remove(sala);

    expect(component.error()).toContain('La sala ha prenotazioni future.');
  });
});
