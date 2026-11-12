import { TestBed } from '@angular/core/testing';
import { HttpErrorResponse } from '@angular/common/http';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { expectNoA11yViolations } from '../../../../testing/a11y';
import { Subscription } from '../../../core/models/models';
import { SubscriptionService } from '../../../core/services/http/subscription.service';
import { UserService } from '../../../core/services/http/user.service';
import { AdminSubscriptionsComponent } from './admin-subscriptions.component';

const plan = { id: 1, name: 'Mensile', description: null, duration_days: 30, max_courses: 2, is_active: true };
const users = { data: [{ id: 4, name: 'Mario Rossi', email: 'mario@example.com', role: 'user' }] };

function subscription(overrides: Partial<Subscription> = {}): Subscription {
  return { id: 10, user_id: 4, subscription_plan_id: 1, starts_on: '2026-11-01', ends_on: '2026-11-30', cancelled_at: null, is_active: true, plan, ...overrides };
}

describe('AdminSubscriptionsComponent', () => {
  let api: { plans: ReturnType<typeof vi.fn>; ofUser: ReturnType<typeof vi.fn>; assign: ReturnType<typeof vi.fn>; cancel: ReturnType<typeof vi.fn> };

  async function render(existing: Subscription[] = []) {
    api = {
      plans: vi.fn().mockReturnValue(of([plan])),
      ofUser: vi.fn().mockReturnValue(of(existing)),
      assign: vi.fn().mockReturnValue(of(subscription())),
      cancel: vi.fn().mockReturnValue(of(subscription({ cancelled_at: '2026-11-12T10:00:00Z', is_active: false }))),
    };

    await TestBed.configureTestingModule({
      imports: [AdminSubscriptionsComponent],
      providers: [{ provide: SubscriptionService, useValue: api }, { provide: UserService, useValue: { list: vi.fn().mockReturnValue(of(users)) } }],
    }).compileComponents();

    const fixture = TestBed.createComponent(AdminSubscriptionsComponent);
    fixture.detectChanges();
    const el = fixture.nativeElement as HTMLElement;

    el.querySelector<HTMLButtonElement>('button[aria-label="Gestisci gli abbonamenti di Mario Rossi"]')!.click();
    fixture.detectChanges();
    return { el, fixture };
  }

  const text = (el: HTMLElement) => el.textContent!.replace(/\s+/g, ' ');

  it('mostra gli utenti e, scelto uno, i suoi abbonamenti con lo stato a parole', async () => {
    const { el } = await render([subscription(), subscription({ id: 11, cancelled_at: '2026-10-01T10:00:00Z', is_active: false })]);

    expect(text(el)).toContain('Abbonamenti di Mario Rossi');
    expect(text(el)).toContain('dal 01/11/2026 al 30/11/2026');
    expect(text(el)).toContain('Attivo');
    expect(text(el)).toContain('Annullato');
  });

  it('segna come "premuto" il pulsante dell\'utente scelto', async () => {
    const { el } = await render();

    expect(el.querySelector('button[aria-label="Gestisci gli abbonamenti di Mario Rossi"]')!.getAttribute('aria-pressed')).toBe('true');
  });

  it('senza abbonamenti lo dice', async () => {
    const { el } = await render([]);

    expect(text(el)).toContain('Questa persona non ha abbonamenti.');
  });

  it('il pulsante di assegnazione resta spento finché non si sceglie un piano', async () => {
    const { el } = await render();

    expect(Array.from(el.querySelectorAll('button')).find((b) => b.textContent!.includes('Assegna abbonamento'))!.disabled).toBe(true);
  });

  it('assegnando un piano chiama l\'API e annuncia l\'esito', async () => {
    const { el, fixture } = await render();
    fixture.componentInstance['planId'].set(1);
    fixture.detectChanges();

    el.querySelector<HTMLFormElement>('form[aria-label="Assegna un abbonamento"]')!.dispatchEvent(new Event('submit'));
    fixture.detectChanges();

    expect(api.assign).toHaveBeenCalledWith(4, 1, undefined);
    expect(el.querySelector('.alert[role="status"]')!.textContent).toContain('Abbonamento assegnato a Mario Rossi.');
  });

  it('se i periodi si sovrappongono mostra il rifiuto del server', async () => {
    const { el, fixture } = await render();
    api.assign.mockReturnValue(throwError(() => new HttpErrorResponse({ status: 422, error: { errors: { starts_on: ['L\'utente ha già un abbonamento in questo periodo.'] } } })));
    fixture.componentInstance['planId'].set(1);

    el.querySelector<HTMLFormElement>('form[aria-label="Assegna un abbonamento"]')!.dispatchEvent(new Event('submit'));
    fixture.detectChanges();

    expect(el.querySelector('.alert--error[role="alert"]')!.textContent).toContain('ha già un abbonamento in questo periodo');
  });

  it('annullando un abbonamento chiede conferma e poi chiama l\'API', async () => {
    const { el, fixture } = await render([subscription()]);
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true);

    el.querySelector<HTMLButtonElement>('button[aria-label^="Annulla l\'abbonamento"]')!.click();
    fixture.detectChanges();

    expect(confirmSpy).toHaveBeenCalled();
    expect(api.cancel).toHaveBeenCalledWith(10);
    confirmSpy.mockRestore();
  });

  it('se non si conferma non annulla nulla', async () => {
    const { el } = await render([subscription()]);
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(false);

    el.querySelector<HTMLButtonElement>('button[aria-label^="Annulla l\'abbonamento"]')!.click();

    expect(api.cancel).not.toHaveBeenCalled();
    confirmSpy.mockRestore();
  });

  it('è accessibile (WCAG 2.2 AA, senza il contrasto)', async () => {
    const { el } = await render([subscription()]);

    await expectNoA11yViolations(el);
  });
});
