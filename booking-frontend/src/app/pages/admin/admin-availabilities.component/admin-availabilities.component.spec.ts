import { TestBed } from '@angular/core/testing';
import { HttpErrorResponse } from '@angular/common/http';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { ActivatedRoute, convertToParamMap, provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { FakeConfirm } from '../../../../testing/confirm';
import { Availability, Bookable } from '../../../core/models/models';
import { ConfirmService } from '../../../core/services/features/confirm.service';
import { BookingService } from '../../../core/services/http/booking.service';
import { AdminAvailabilitiesComponent } from './admin-availabilities.component';

const fascia: Availability = { id: 11, bookable_id: 3, day_of_week: 1, start_time: '09:00:00', end_time: '13:00:00' };
const sala: Bookable = { id: 3, name: 'Sala pesi', slot_minutes: 60, is_active: true, availabilities: [fascia] };

describe('AdminAvailabilitiesComponent: eliminare una fascia oraria', () => {
  const confirmer = new FakeConfirm();
  const snack = { open: vi.fn() };
  let api: { getBookable: ReturnType<typeof vi.fn>; deleteAvailability: ReturnType<typeof vi.fn> };

  async function render() {
    confirmer.confirm.mockClear();
    snack.open.mockClear();
    api = { getBookable: vi.fn().mockReturnValue(of(sala)), deleteAvailability: vi.fn().mockReturnValue(of(undefined)) };

    await TestBed.configureTestingModule({
      imports: [AdminAvailabilitiesComponent],
      providers: [
        provideRouter([]),
        { provide: ActivatedRoute, useValue: { snapshot: { paramMap: convertToParamMap({ id: '3' }) } } },
        { provide: BookingService, useValue: api },
        { provide: ConfirmService, useValue: confirmer },
        { provide: MatSnackBar, useValue: snack },
      ],
    })
      // Il componente importa MatSnackBarModule, che porterebbe una sua copia dello snackbar: si toglie per usare il finto
      .overrideComponent(AdminAvailabilitiesComponent, { remove: { imports: [MatSnackBarModule] } })
      .compileComponents();

    const fixture = TestBed.createComponent(AdminAvailabilitiesComponent);
    fixture.detectChanges();
    return fixture.componentInstance;
  }

  it('chiede conferma indicando gli orari, poi elimina la fascia dall\'elenco', async () => {
    confirmer.answer = true;
    const component = await render();

    component.remove(fascia);

    expect(confirmer.confirm).toHaveBeenCalledWith(expect.objectContaining({
      confirmLabel: 'Elimina fascia',
      message: expect.stringContaining('09:00–13:00'),
    }));
    expect(api.deleteAvailability).toHaveBeenCalledWith(11);
    expect(component.availabilities()).toEqual([]);
    expect(snack.open).toHaveBeenCalledWith('Fascia eliminata', 'Chiudi', expect.anything());
  });

  it('se l\'utente rinuncia la fascia resta', async () => {
    confirmer.answer = false;
    const component = await render();

    component.remove(fascia);

    expect(api.deleteAvailability).not.toHaveBeenCalled();
    expect(component.availabilities()).toEqual([fascia]);
  });

  it('se il server rifiuta mostra il motivo e la fascia resta', async () => {
    confirmer.answer = true;
    const component = await render();
    api.deleteAvailability.mockReturnValue(throwError(() => new HttpErrorResponse({ status: 422, error: { message: 'Non eliminabile.' } })));

    component.remove(fascia);

    expect(snack.open).toHaveBeenCalledWith(expect.stringContaining('Non eliminabile.'), 'Chiudi', expect.anything());
    expect(component.availabilities()).toEqual([fascia]);
  });
});
