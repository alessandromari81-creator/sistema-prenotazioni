import { TestBed } from '@angular/core/testing';
import { HttpErrorResponse } from '@angular/common/http';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { FakeConfirm } from '../../../../testing/confirm';
import { Booking } from '../../../core/models/models';
import { ConfirmService } from '../../../core/services/features/confirm.service';
import { BookingService } from '../../../core/services/http/booking.service';
import { MyBookingsComponent } from './my-bookings.component';

const future = new Date(Date.now() + 3 * 24 * 3600 * 1000).toISOString();
const booking: Booking = { id: 7, bookable_id: 1, starts_at: future, status: 'confirmed', bookable: { id: 1, name: 'Sala pesi', slot_minutes: 60, is_active: true } };

describe('MyBookingsComponent: annullare una prenotazione', () => {
  const confirmer = new FakeConfirm();
  const snack = { open: vi.fn() };
  let api: { getMyBookings: ReturnType<typeof vi.fn>; cancelBooking: ReturnType<typeof vi.fn> };

  async function render() {
    confirmer.confirm.mockClear();
    snack.open.mockClear();
    api = {
      getMyBookings: vi.fn().mockReturnValue(of({ data: [booking], total: 1 })),
      cancelBooking: vi.fn().mockReturnValue(of(undefined)),
    };

    await TestBed.configureTestingModule({
      imports: [MyBookingsComponent],
      providers: [{ provide: BookingService, useValue: api }, { provide: ConfirmService, useValue: confirmer }, { provide: MatSnackBar, useValue: snack }],
    })
      // Il componente importa MatSnackBarModule, che porterebbe una sua copia dello snackbar: si toglie per usare il finto
      .overrideComponent(MyBookingsComponent, { remove: { imports: [MatSnackBarModule] } })
      .compileComponents();

    const fixture = TestBed.createComponent(MyBookingsComponent);
    fixture.detectChanges();
    return fixture.componentInstance;
  }

  it('chiede conferma con una finestra e, se si conferma, annulla e segna la riga come annullata', async () => {
    confirmer.answer = true;
    const component = await render();

    component.cancel(booking);

    expect(confirmer.confirm).toHaveBeenCalledWith(expect.objectContaining({ confirmLabel: 'Annulla prenotazione' }));
    expect(api.cancelBooking).toHaveBeenCalledWith(7);
    expect(component.bookings()[0].status).toBe('cancelled');
    expect(snack.open).toHaveBeenCalledWith('Prenotazione annullata', 'Chiudi', expect.anything());
  });

  it('se l\'utente rinuncia non annulla nulla', async () => {
    confirmer.answer = false;
    const component = await render();

    component.cancel(booking);

    expect(api.cancelBooking).not.toHaveBeenCalled();
    expect(component.bookings()[0].status).toBe('confirmed');
  });

  it('se il server rifiuta mostra il motivo e la riga resta confermata', async () => {
    confirmer.answer = true;
    const component = await render();
    api.cancelBooking.mockReturnValue(throwError(() => new HttpErrorResponse({ status: 422, error: { message: 'Troppo tardi per annullare.' } })));

    component.cancel(booking);

    expect(snack.open).toHaveBeenCalledWith(expect.stringContaining('Troppo tardi per annullare.'), 'Chiudi', expect.anything());
    expect(component.bookings()[0].status).toBe('confirmed');
  });
});
