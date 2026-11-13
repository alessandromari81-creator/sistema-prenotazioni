import { TestBed } from '@angular/core/testing';
import { HttpErrorResponse } from '@angular/common/http';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { expectNoA11yViolations } from '../../../../testing/a11y';
import { Subscription } from '../../../core/models/models';
import { ConfirmService } from '../../../core/services/features/confirm.service';
import { NotificationService } from '../../../core/services/features/notification.service';
import { SubscriptionService } from '../../../core/services/http/subscription.service';
import { UserSubscriptionsDialogComponent } from './user-subscriptions-dialog.component';

const plan = { id: 1, name: 'Mensile', description: null, duration_days: 30, max_courses: 2, is_active: true };
const user = { id: 4, name: 'Mario Rossi', email: 'mario@example.com', role: 'user' };

function subscription(overrides: Partial<Subscription> = {}): Subscription {
  return { id: 10, user_id: 4, subscription_plan_id: 1, starts_on: '2026-11-01', ends_on: '2026-11-30', cancelled_at: null, is_active: true, plan, ...overrides };
}

describe('UserSubscriptionsDialogComponent', () => {
  // Gli avvisi a comparsa (snackbar) si provano sul servizio: qui non c'è nessun avviso fisso nella finestra
  const notify = { success: vi.fn(), error: vi.fn() };
  beforeEach(() => {
    notify.success.mockReset();
    notify.error.mockReset();
    confirmAnswer = of(true);
  });

  let confirmAnswer = of(true);
  let api: { plans: ReturnType<typeof vi.fn>; ofUser: ReturnType<typeof vi.fn>; assign: ReturnType<typeof vi.fn>; cancel: ReturnType<typeof vi.fn> };

  async function render(existing: Subscription[] = []) {
    api = {
      plans: vi.fn().mockReturnValue(of([plan])),
      ofUser: vi.fn().mockReturnValue(of(existing)),
      assign: vi.fn().mockReturnValue(of(subscription())),
      cancel: vi.fn().mockReturnValue(of(subscription({ cancelled_at: '2026-11-12T10:00:00Z', is_active: false }))),
    };

    await TestBed.configureTestingModule({
      imports: [UserSubscriptionsDialogComponent],
      providers: [
        { provide: NotificationService, useValue: notify },
        { provide: ConfirmService, useValue: { confirm: vi.fn(() => confirmAnswer) } },
        { provide: SubscriptionService, useValue: api },
        { provide: MAT_DIALOG_DATA, useValue: { user } },
        { provide: MatDialogRef, useValue: { close: vi.fn() } },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(UserSubscriptionsDialogComponent);
    fixture.detectChanges();
    return { el: fixture.nativeElement as HTMLElement, fixture };
  }

  const text = (el: HTMLElement) => el.textContent!.replace(/\s+/g, ' ');

  it('mostra il titolo con il nome e gli abbonamenti con lo stato a parole', async () => {
    const { el } = await render([subscription(), subscription({ id: 11, cancelled_at: '2026-10-01T10:00:00Z', is_active: false })]);

    expect(text(el)).toContain('Abbonamenti di Mario Rossi');
    expect(text(el)).toContain('mario@example.com');
    expect(api.ofUser).toHaveBeenCalledWith(4);
    expect(text(el)).toContain('dal 01/11/2026 al 30/11/2026');
    expect(text(el)).toContain('Attivo');
    expect(text(el)).toContain('Annullato');
  });

  it('senza abbonamenti lo dice', async () => {
    const { el } = await render([]);

    expect(text(el)).toContain('Questa persona non ha abbonamenti.');
  });

  it('senza un piano non chiama l\'API e dice di sceglierne uno', async () => {
    const { el, fixture } = await render();

    el.querySelector<HTMLFormElement>('form[aria-label="Assegna un abbonamento"]')!.dispatchEvent(new Event('submit'));
    fixture.detectChanges();

    expect(api.assign).not.toHaveBeenCalled();
    expect(text(el)).toContain('Scegli un piano.');
  });

  it('una data di inizio nel passato non è valida e non parte nessuna richiesta', async () => {
    const { el, fixture } = await render();
    fixture.componentInstance['form'].setValue({ plan: 1, startsOn: '2020-01-01' });

    el.querySelector<HTMLFormElement>('form[aria-label="Assegna un abbonamento"]')!.dispatchEvent(new Event('submit'));
    fixture.detectChanges();

    expect(api.assign).not.toHaveBeenCalled();
    expect(text(el)).toContain('La data non può essere nel passato.');
  });

  it('con una data di inizio valida la manda al server', async () => {
    const { el, fixture } = await render();
    fixture.componentInstance['form'].setValue({ plan: 1, startsOn: '2999-01-01' });

    el.querySelector<HTMLFormElement>('form[aria-label="Assegna un abbonamento"]')!.dispatchEvent(new Event('submit'));

    expect(api.assign).toHaveBeenCalledWith(4, 1, '2999-01-01');
  });

  it('dopo l\'assegnazione il modulo si svuota', async () => {
    const { el, fixture } = await render();
    fixture.componentInstance['form'].setValue({ plan: 1, startsOn: '2999-01-01' });

    el.querySelector<HTMLFormElement>('form[aria-label="Assegna un abbonamento"]')!.dispatchEvent(new Event('submit'));

    expect(fixture.componentInstance['form'].getRawValue()).toEqual({ plan: null, startsOn: '' });
  });

  it('assegnando un piano chiama l\'API, avvisa e ricarica gli abbonamenti', async () => {
    const { el, fixture } = await render();
    fixture.componentInstance['form'].patchValue({ plan: 1 });
    fixture.detectChanges();

    el.querySelector<HTMLFormElement>('form[aria-label="Assegna un abbonamento"]')!.dispatchEvent(new Event('submit'));
    fixture.detectChanges();

    expect(api.assign).toHaveBeenCalledWith(4, 1, undefined);
    expect(notify.success).toHaveBeenCalledWith(expect.stringContaining('Abbonamento assegnato a Mario Rossi.'));
    expect(api.ofUser).toHaveBeenCalledTimes(2);
  });

  it('se i periodi si sovrappongono mostra il rifiuto del server', async () => {
    const { el, fixture } = await render();
    api.assign.mockReturnValue(throwError(() => new HttpErrorResponse({ status: 422, error: { errors: { starts_on: ['L\'utente ha già un abbonamento in questo periodo.'] } } })));
    fixture.componentInstance['form'].patchValue({ plan: 1 });

    el.querySelector<HTMLFormElement>('form[aria-label="Assegna un abbonamento"]')!.dispatchEvent(new Event('submit'));
    fixture.detectChanges();

    expect(notify.error).toHaveBeenCalledWith(expect.stringContaining('ha già un abbonamento in questo periodo'));
  });

  it('annullando un abbonamento chiede conferma e poi chiama l\'API', async () => {
    confirmAnswer = of(true);
    const { el, fixture } = await render([subscription()]);
    const confirmService = TestBed.inject(ConfirmService);

    el.querySelector<HTMLButtonElement>('button[aria-label^="Annulla l\'abbonamento"]')!.click();
    fixture.detectChanges();

    expect(confirmService.confirm).toHaveBeenCalledWith(expect.objectContaining({ confirmLabel: 'Annulla abbonamento', message: expect.stringContaining('Mario Rossi') }));
    expect(api.cancel).toHaveBeenCalledWith(10);
    expect(notify.success).toHaveBeenCalledWith('Abbonamento annullato.');
  });

  it('se il server rifiuta l\'annullamento mostra il motivo', async () => {
    const { el, fixture } = await render([subscription()]);
    api.cancel.mockReturnValue(throwError(() => new HttpErrorResponse({ status: 422, error: { message: 'Abbonamento già annullato.' } })));

    el.querySelector<HTMLButtonElement>('button[aria-label^="Annulla l\'abbonamento"]')!.click();
    fixture.detectChanges();

    expect(notify.error).toHaveBeenCalledWith(expect.stringContaining('Abbonamento già annullato.'));
  });

  it('se non si conferma non annulla nulla', async () => {
    confirmAnswer = of(false);
    const { el } = await render([subscription()]);

    el.querySelector<HTMLButtonElement>('button[aria-label^="Annulla l\'abbonamento"]')!.click();

    expect(api.cancel).not.toHaveBeenCalled();
  });

  it('un abbonamento già annullato non ha il pulsante "Annulla"', async () => {
    const { el } = await render([subscription({ cancelled_at: '2026-10-01T10:00:00Z', is_active: false })]);

    expect(el.querySelector('button[aria-label^="Annulla l\'abbonamento"]')).toBeNull();
  });

  it('ha il pulsante "Chiudi"', async () => {
    const { el } = await render();

    expect(Array.from(el.querySelectorAll('button')).some((b) => b.textContent!.trim() === 'Chiudi')).toBe(true);
  });

  it('è accessibile (WCAG 2.2 AA, senza il contrasto)', async () => {
    const { el } = await render([subscription()]);

    await expectNoA11yViolations(el);
  });
});
