import { TestBed } from '@angular/core/testing';
import { HttpErrorResponse } from '@angular/common/http';
import { provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { expectNoA11yViolations } from '../../../../testing/a11y';
import { SubscriptionService } from '../../../core/services/http/subscription.service';
import { AdminPlansComponent } from './admin-plans.component';
import { ConfirmService } from '../../../core/services/features/confirm.service';
import { NotificationService } from '../../../core/services/features/notification.service';
import { FakeConfirm } from '../../../../testing/confirm';

const plans = [
  { id: 1, name: 'Mensile', description: null, duration_days: 30, max_courses: 2, is_active: true },
  { id: 2, name: 'Vecchio', description: null, duration_days: 90, max_courses: 4, is_active: false },
];

describe('AdminPlansComponent', () => {
  // Gli avvisi a comparsa (snackbar) si provano sul servizio: qui non c'è nessun avviso fisso nella pagina
  const notify = { success: vi.fn(), error: vi.fn() };
  const confirmer = new FakeConfirm(); // la finestra di conferma: ogni test decide se l'utente conferma
  beforeEach(() => {
    notify.success.mockReset();
    notify.error.mockReset();
  });

  let api: { plans: ReturnType<typeof vi.fn>; deletePlan: ReturnType<typeof vi.fn> };

  async function render() {
    api = { plans: vi.fn().mockReturnValue(of(plans)), deletePlan: vi.fn().mockReturnValue(of(undefined)) };

    await TestBed.configureTestingModule({
      imports: [AdminPlansComponent],
      providers: [{ provide: NotificationService, useValue: notify }, { provide: ConfirmService, useValue: confirmer }, provideRouter([]), { provide: SubscriptionService, useValue: api }],
    }).compileComponents();

    const fixture = TestBed.createComponent(AdminPlansComponent);
    fixture.detectChanges();
    return { el: fixture.nativeElement as HTMLElement, fixture };
  }

  const text = (el: HTMLElement) => el.textContent!.replace(/\s+/g, ' ');

  it('carica anche i piani disattivati e li mostra con lo stato a parole', async () => {
    const { el } = await render();

    expect(api.plans).toHaveBeenCalledWith(true);
    expect(text(el)).toContain('Mensile');
    expect(text(el)).toContain('30 giorni');
    expect(text(el)).toContain('Attivo');
    expect(text(el)).toContain('Disattivato');
  });

  it('la tabella ha intestazioni di colonna e le azioni hanno un nome', async () => {
    const { el } = await render();

    expect(el.querySelectorAll('th[scope="col"]').length).toBeGreaterThanOrEqual(4);
    expect(el.querySelector('a[aria-label="Modifica il piano Mensile"]')).not.toBeNull();
    expect(el.querySelector('button[aria-label="Elimina il piano Mensile"]')).not.toBeNull();
  });

  it('eliminando un piano chiede conferma', async () => {
    const { el, fixture } = await render();
    confirmer.answer = true;

    el.querySelector<HTMLButtonElement>('button[aria-label="Elimina il piano Mensile"]')!.click();
    fixture.detectChanges();

    expect(confirmer.confirm).toHaveBeenCalled();
    expect(api.deletePlan).toHaveBeenCalledWith(1);
    expect(notify.success).toHaveBeenCalledWith(expect.stringContaining('Il piano Mensile è stato eliminato.'));
  });

  it('se il piano è già stato assegnato mostra il rifiuto del server', async () => {
    const { el, fixture } = await render();
    api.deletePlan.mockReturnValue(throwError(() => new HttpErrorResponse({
      status: 422,
      error: { errors: { subscription_plan: ['Il piano è stato assegnato a degli utenti: disattivalo invece di eliminarlo.'] } },
    })));
    confirmer.answer = true;

    el.querySelector<HTMLButtonElement>('button[aria-label="Elimina il piano Mensile"]')!.click();
    fixture.detectChanges();

    expect(notify.error).toHaveBeenCalledWith(expect.stringContaining('disattivalo invece di eliminarlo'));
  });

  it('è accessibile (WCAG 2.2 AA, senza il contrasto)', async () => {
    const { el } = await render();

    await expectNoA11yViolations(el);
  });
});
