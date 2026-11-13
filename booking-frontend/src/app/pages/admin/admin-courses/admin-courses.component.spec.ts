import { TestBed } from '@angular/core/testing';
import { HttpErrorResponse } from '@angular/common/http';
import { provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { expectNoA11yViolations } from '../../../../testing/a11y';
import { Course } from '../../../core/models/models';
import { CourseService } from '../../../core/services/http/course.service';
import { AdminCoursesComponent } from './admin-courses.component';
import { ConfirmService } from '../../../core/services/features/confirm.service';
import { NotificationService } from '../../../core/services/features/notification.service';
import { FakeConfirm } from '../../../../testing/confirm';

const yoga: Course = {
  id: 7, name: 'Yoga', description: null, capacity: 12, day_of_week: 1, start_time: '18:30:00', duration_minutes: 60,
  is_active: true, bookable_id: null, enrolled_count: 5,
};
const old: Course = { ...yoga, id: 8, name: 'Corso chiuso', is_active: false, enrolled_count: 0 };

function page(data: Course[]) {
  return { current_page: 1, data, first_page_url: null, last_page: 1, last_page_url: null, per_page: 10, total: data.length, from: 1, to: data.length, next_page_url: null, prev_page_url: null };
}

describe('AdminCoursesComponent', () => {
  // Gli avvisi a comparsa (snackbar) si provano sul servizio: qui non c'è nessun avviso fisso nella pagina
  const notify = { success: vi.fn(), error: vi.fn() };
  const confirmer = new FakeConfirm(); // la finestra di conferma: ogni test decide se l'utente conferma
  beforeEach(() => {
    notify.success.mockReset();
    notify.error.mockReset();
  });

  let api: { list: ReturnType<typeof vi.fn>; delete: ReturnType<typeof vi.fn> };

  async function render() {
    api = { list: vi.fn().mockReturnValue(of(page([yoga, old]))), delete: vi.fn().mockReturnValue(of(undefined)) };

    await TestBed.configureTestingModule({
      imports: [AdminCoursesComponent],
      providers: [{ provide: NotificationService, useValue: notify }, { provide: ConfirmService, useValue: confirmer }, provideRouter([]), { provide: CourseService, useValue: api }],
    }).compileComponents();

    const fixture = TestBed.createComponent(AdminCoursesComponent);
    fixture.detectChanges();
    return { el: fixture.nativeElement as HTMLElement, fixture };
  }

  const text = (el: HTMLElement) => el.textContent!.replace(/\s+/g, ' ');

  it('chiede anche i corsi disattivati e li mostra con quando, iscritti e stato', async () => {
    const { el } = await render();

    expect(api.list).toHaveBeenCalledWith(expect.objectContaining({ includeInactive: true }));
    expect(text(el)).toContain('Lunedì dalle 18:30 alle 19:30');
    expect(text(el)).toContain('5 / 12');
    expect(text(el)).toContain('Disattivato');
  });

  it('le azioni di ogni riga hanno un nome che dice su quale corso agiscono', async () => {
    const { el } = await render();

    for (const label of ['Istruttori di Yoga', 'Modifica Yoga', 'Elimina Yoga']) {
      expect(el.querySelector(`[aria-label="${label}"]`), label).not.toBeNull();
    }
  });

  it('eliminando un corso chiede conferma e annuncia l\'esito', async () => {
    const { el, fixture } = await render();
    confirmer.answer = true;

    el.querySelector<HTMLButtonElement>('button[aria-label="Elimina Yoga"]')!.click();
    fixture.detectChanges();

    expect(api.delete).toHaveBeenCalledWith(7);
    expect(notify.success).toHaveBeenCalledWith(expect.stringContaining('Il corso Yoga è stato eliminato.'));
  });

  it('se il corso ha iscritti mostra il rifiuto del server', async () => {
    const { el, fixture } = await render();
    api.delete.mockReturnValue(throwError(() => new HttpErrorResponse({ status: 422, error: { errors: { course: ['Il corso ha 5 iscritti: disattivalo invece di eliminarlo.'] } } })));
    confirmer.answer = true;

    el.querySelector<HTMLButtonElement>('button[aria-label="Elimina Yoga"]')!.click();
    fixture.detectChanges();

    expect(notify.error).toHaveBeenCalledWith(expect.stringContaining('Il corso ha 5 iscritti'));
  });

  it('se non si conferma non elimina nulla', async () => {
    const { el } = await render();
    confirmer.answer = false;

    el.querySelector<HTMLButtonElement>('button[aria-label="Elimina Yoga"]')!.click();

    expect(api.delete).not.toHaveBeenCalled();
  });

  it('è accessibile (WCAG 2.2 AA, senza il contrasto)', async () => {
    const { el } = await render();

    await expectNoA11yViolations(el);
  });
});
