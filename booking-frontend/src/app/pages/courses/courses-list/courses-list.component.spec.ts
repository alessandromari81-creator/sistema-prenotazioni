import { ComponentFixture, TestBed } from '@angular/core/testing';
import { HttpErrorResponse } from '@angular/common/http';
import { provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { expectNoA11yViolations } from '../../../../testing/a11y';
import { Course } from '../../../core/models/models';
import { AuthService } from '../../../core/services/http/auth.service';
import { CourseService } from '../../../core/services/http/course.service';
import { CoursesListComponent } from './courses-list.component';
import { NotificationService } from '../../../core/services/features/notification.service';

function course(id: number, overrides: Partial<Course> = {}): Course {
  return {
    id,
    name: `Corso ${id}`,
    description: null,
    capacity: 10,
    day_of_week: 2,
    start_time: '18:00:00',
    duration_minutes: 60,
    is_active: true,
    bookable_id: null,
    instructors: [{ id: 99, name: 'Sara Neri' }],
    enrolled_count: 3,
    spots_left: 7,
    is_enrolled: false,
    is_instructor: false,
    ...overrides,
  };
}

/** Una pagina di risultati come la restituisce l'API. */
function page(data: Course[]) {
  return { current_page: 1, data, first_page_url: null, last_page: 1, last_page_url: null, per_page: 6, total: data.length, from: 1, to: data.length, next_page_url: null, prev_page_url: null };
}

describe('CoursesListComponent', () => {
  // Gli avvisi a comparsa (snackbar) si provano sul servizio: qui non c'è nessun avviso fisso nella pagina
  const notify = { success: vi.fn(), error: vi.fn() };
  beforeEach(() => {
    notify.success.mockReset();
    notify.error.mockReset();
  });

  let fixture: ComponentFixture<CoursesListComponent>;
  let api: { list: ReturnType<typeof vi.fn>; enroll: ReturnType<typeof vi.fn>; cancelEnrollment: ReturnType<typeof vi.fn> };
  let el: HTMLElement;

  async function setup(courses: Course[], isAdmin = false) {
    api = { list: vi.fn().mockReturnValue(of(page(courses))), enroll: vi.fn(), cancelEnrollment: vi.fn() };

    await TestBed.configureTestingModule({
      imports: [CoursesListComponent],
      providers: [{ provide: NotificationService, useValue: notify }, provideRouter([]), { provide: CourseService, useValue: api }, { provide: AuthService, useValue: { isAdmin: () => isAdmin } }],
    }).compileComponents();

    fixture = TestBed.createComponent(CoursesListComponent);
    fixture.detectChanges();
    el = fixture.nativeElement as HTMLElement;
  }

  const button = (label: string) => el.querySelector<HTMLButtonElement>(`button[aria-label="${label}"]`);
  const text = () => el.textContent!.replace(/\s+/g, ' ');

  it('carica la prima pagina all\'apertura e mostra le schede', async () => {
    await setup([course(1), course(2)]);

    expect(api.list).toHaveBeenCalledWith(expect.objectContaining({ page: 1, includeInactive: false }));
    expect(el.querySelectorAll('app-course-card')).toHaveLength(2);
    expect(el.querySelector('h1')!.textContent).toBe('Corsi');
  });

  it('chiede anche i corsi disattivati se chi guarda è un amministratore', async () => {
    await setup([course(1)], true);

    expect(api.list).toHaveBeenCalledWith(expect.objectContaining({ includeInactive: true }));
  });

  it('annuncia il numero di risultati in una zona "live" per i lettori di schermo', async () => {
    await setup([course(1), course(2)]);

    expect(el.querySelector('.sr-only[role="status"]')!.textContent).toBe('2 corsi trovati');
  });

  it('dice quando non ci sono corsi', async () => {
    await setup([]);

    expect(text()).toContain('Nessun corso trovato.');
  });

  it('i pulsanti hanno un nome che dice su quale corso agiscono', async () => {
    await setup([course(1, { name: 'Yoga' })]);

    expect(button('Iscriviti a Yoga')).not.toBeNull();
  });

  it('per un corso a cui sei iscritto offre "Annulla iscrizione" e non "Iscriviti"', async () => {
    await setup([course(1, { name: 'Yoga', is_enrolled: true })]);

    expect(button('Annulla iscrizione a Yoga')).not.toBeNull();
    expect(button('Iscriviti a Yoga')).toBeNull();
  });

  it('non offre pulsanti se sei l\'istruttore del corso: lo stato lo spiega', async () => {
    await setup([course(1, { name: 'Yoga', is_instructor: true })]);

    expect(el.querySelectorAll('app-course-card button')).toHaveLength(0);
    expect(text()).toContain('Sei istruttore di questo corso');
  });

  it('non offre l\'iscrizione a un corso al completo', async () => {
    await setup([course(1, { name: 'Yoga', spots_left: 0 })]);

    expect(button('Iscriviti a Yoga')).toBeNull();
    expect(text()).toContain('Al completo');
  });

  it('iscrivendosi aggiorna la scheda e annuncia l\'esito', async () => {
    await setup([course(1, { name: 'Yoga' })]);
    const updated = course(1, { name: 'Yoga', is_enrolled: true, spots_left: 6, enrolled_count: 4 });
    api.enroll.mockReturnValue(of({ id: 1, course_id: 1, user_id: 1, status: 'active', course: updated, created_at: null }));

    button('Iscriviti a Yoga')!.click();
    fixture.detectChanges();

    expect(api.enroll).toHaveBeenCalledWith(1);
    expect(text()).toContain('Sei iscritto');
    expect(text()).toContain('6 posti liberi');
    expect(notify.success).toHaveBeenCalledWith(expect.stringContaining('Iscrizione al corso Yoga completata.'));
  });

  it('se il server rifiuta mostra il motivo in un avviso (role="alert")', async () => {
    await setup([course(1, { name: 'Yoga' })]);
    const refusal = new HttpErrorResponse({ status: 422, error: { errors: { subscription: ['Per iscriverti serve un abbonamento attivo.'] } } });
    api.enroll.mockReturnValue(throwError(() => refusal));

    button('Iscriviti a Yoga')!.click();
    fixture.detectChanges();

    expect(notify.error).toHaveBeenCalledWith(expect.stringContaining('Per iscriverti serve un abbonamento attivo.'));
  });

  it('dopo un\'azione non resta nessun avviso fisso nella pagina (l\'esito è nella snackbar)', async () => {
    await setup([course(1, { name: 'Yoga' })]);
    api.enroll.mockReturnValue(of({ id: 1, course_id: 1, user_id: 1, status: 'active', course: course(1, { name: 'Yoga', is_enrolled: true }), created_at: null }));

    button('Iscriviti a Yoga')!.click();
    fixture.detectChanges();

    expect(notify.success).toHaveBeenCalledWith('Iscrizione al corso Yoga completata.');
    expect(el.querySelectorAll('.alert')).toHaveLength(0);
  });

  it('annullando l\'iscrizione aggiorna la scheda', async () => {
    await setup([course(1, { name: 'Yoga', is_enrolled: true })]);
    const updated = course(1, { name: 'Yoga', is_enrolled: false, spots_left: 8 });
    api.cancelEnrollment.mockReturnValue(of({ id: 1, course_id: 1, user_id: 1, status: 'cancelled', course: updated, created_at: null }));

    button('Annulla iscrizione a Yoga')!.click();
    fixture.detectChanges();

    expect(button('Iscriviti a Yoga')).not.toBeNull();
    expect(notify.success).toHaveBeenCalledWith('Iscrizione al corso Yoga annullata.');
  });

  it('se il caricamento fallisce mostra un avviso', async () => {
    api = { list: vi.fn().mockReturnValue(throwError(() => new HttpErrorResponse({ status: 500, error: null }))), enroll: vi.fn(), cancelEnrollment: vi.fn() };
    await TestBed.configureTestingModule({
      imports: [CoursesListComponent],
      providers: [{ provide: NotificationService, useValue: notify }, provideRouter([]), { provide: CourseService, useValue: api }, { provide: AuthService, useValue: { isAdmin: () => false } }],
    }).compileComponents();
    fixture = TestBed.createComponent(CoursesListComponent);
    fixture.detectChanges();

    expect((fixture.nativeElement as HTMLElement).querySelector('[role="alert"]')!.textContent).toContain('Impossibile caricare i corsi.');
  });

  it('è accessibile (WCAG 2.2 AA, senza il contrasto)', async () => {
    await setup([course(1, { is_enrolled: true }), course(2), course(3, { spots_left: 0 })]);

    await expectNoA11yViolations(el);
  });
});
