import { ComponentFixture, TestBed } from '@angular/core/testing';
import { HttpErrorResponse } from '@angular/common/http';
import { ActivatedRoute, convertToParamMap, provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { expectNoA11yViolations } from '../../../../testing/a11y';
import { Course, Enrollment } from '../../../core/models/models';
import { AuthService } from '../../../core/services/http/auth.service';
import { CourseService } from '../../../core/services/http/course.service';
import { CourseDetailComponent } from './course-detail.component';
import { NotificationService } from '../../../core/services/features/notification.service';

function course(overrides: Partial<Course> = {}): Course {
  return {
    id: 7, name: 'Yoga', description: 'Posture e respirazione.', capacity: 12, day_of_week: 1, start_time: '18:30:00',
    duration_minutes: 60, is_active: true, bookable_id: null, instructors: [{ id: 2, name: 'Luca Verdi' }],
    enrolled_count: 4, spots_left: 8, is_enrolled: false, is_instructor: false, ...overrides,
  };
}

const students: Enrollment[] = [
  { id: 1, course_id: 7, user_id: 4, status: 'active', created_at: null, student: { id: 4, name: 'Mario Rossi', email: 'mario@example.com' } },
];

describe('CourseDetailComponent', () => {
  // Gli avvisi a comparsa (snackbar) si provano sul servizio: qui non c'è nessun avviso fisso nella pagina
  const notify = { success: vi.fn(), error: vi.fn() };
  beforeEach(() => {
    notify.success.mockReset();
    notify.error.mockReset();
  });

  let fixture: ComponentFixture<CourseDetailComponent>;
  let api: { get: ReturnType<typeof vi.fn>; students: ReturnType<typeof vi.fn>; enroll: ReturnType<typeof vi.fn>; cancelEnrollment: ReturnType<typeof vi.fn> };

  async function render(c: Course | Error, isAdmin = false): Promise<HTMLElement> {
    api = {
      get: vi.fn().mockReturnValue(c instanceof Error ? throwError(() => new HttpErrorResponse({ status: 404, error: { message: 'Not Found' } })) : of(c)),
      students: vi.fn().mockReturnValue(of(students)),
      enroll: vi.fn(),
      cancelEnrollment: vi.fn(),
    };

    await TestBed.configureTestingModule({
      imports: [CourseDetailComponent],
      providers: [{ provide: NotificationService, useValue: notify }, 
        provideRouter([]),
        { provide: CourseService, useValue: api },
        { provide: AuthService, useValue: { isAdmin: () => isAdmin } },
        { provide: ActivatedRoute, useValue: { snapshot: { paramMap: convertToParamMap({ id: '7' }) } } },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(CourseDetailComponent);
    fixture.detectChanges();
    return fixture.nativeElement as HTMLElement;
  }

  const text = (el: HTMLElement) => el.textContent!.replace(/\s+/g, ' ');

  it('mostra i dettagli del corso', async () => {
    const el = await render(course());

    expect(el.querySelector('h1')!.textContent).toBe('Yoga');
    expect(text(el)).toContain('Lunedì dalle 18:30 alle 19:30');
    expect(text(el)).toContain('Istruttore: Luca Verdi');
    expect(text(el)).toContain('4 iscritti su 12 posti');
    expect(text(el)).toContain('8 posti liberi');
  });

  it('offre l\'iscrizione a un utente normale e non mostra gli iscritti', async () => {
    const el = await render(course());

    expect(text(el)).toContain('Iscriviti al corso');
    expect(el.querySelector('#students-title')).toBeNull();
    expect(api.students).not.toHaveBeenCalled();
  });

  it('l\'istruttore del corso vede gli iscritti ma non può iscriversi', async () => {
    const el = await render(course({ is_instructor: true }));

    expect(text(el)).not.toContain('Iscriviti al corso');
    expect(api.students).toHaveBeenCalledWith(7);
    expect(text(el)).toContain('Mario Rossi');
    expect(text(el)).toContain('mario@example.com');
  });

  it('l\'amministratore vede gli iscritti e i collegamenti di gestione', async () => {
    const el = await render(course(), true);

    expect(text(el)).toContain('Mario Rossi');
    expect(el.querySelector('a[href="/admin/courses/7/instructors"]')).not.toBeNull();
    expect(el.querySelector('a[href="/admin/courses/7/edit"]')).not.toBeNull();
  });

  it('la tabella degli iscritti ha intestazioni di colonna', async () => {
    const el = await render(course({ is_instructor: true }));

    const headers = Array.from(el.querySelectorAll('th')).map((th) => th.textContent!.trim());
    expect(headers).toEqual(['Nome', 'Email']);
  });

  it('iscrivendosi aggiorna il corso e annuncia l\'esito', async () => {
    const el = await render(course());
    api.enroll.mockReturnValue(of(undefined)); // 204 senza corpo
    api.get.mockReturnValue(of(course({ is_enrolled: true, spots_left: 7, enrolled_count: 5 }))); // i dati aggiornati si rileggono

    Array.from(el.querySelectorAll('button')).find((b) => b.textContent!.includes('Iscriviti'))!.click();
    fixture.detectChanges();

    expect(api.enroll).toHaveBeenCalledWith(7);
    expect(text(el)).toContain('Sei iscritto');
    expect(text(el)).toContain('7 posti liberi');
    expect(notify.success).toHaveBeenCalledWith(expect.stringContaining('Iscrizione completata.'));
    expect(text(el)).toContain('Annulla iscrizione');
  });

  it('annullando l\'iscrizione il corso torna iscrivibile', async () => {
    const el = await render(course({ is_enrolled: true }));
    api.cancelEnrollment.mockReturnValue(of(undefined)); // 204 senza corpo
    api.get.mockReturnValue(of(course({ is_enrolled: false }))); // i dati aggiornati si rileggono

    Array.from(el.querySelectorAll('button')).find((b) => b.textContent!.includes('Annulla iscrizione'))!.click();
    fixture.detectChanges();

    expect(api.cancelEnrollment).toHaveBeenCalledWith(7);
    expect(text(el)).toContain('Iscriviti al corso');
    expect(notify.success).toHaveBeenCalledWith(expect.stringContaining('Iscrizione annullata.'));
  });

  it('se il server rifiuta l\'iscrizione mostra il motivo', async () => {
    const el = await render(course());
    const refusal = new HttpErrorResponse({ status: 422, error: { errors: { course: ['Il corso è al completo.'] } } });
    api.enroll.mockReturnValue(throwError(() => refusal));

    Array.from(el.querySelectorAll('button')).find((b) => b.textContent!.includes('Iscriviti'))!.click();
    fixture.detectChanges();

    expect(notify.error).toHaveBeenCalledWith(expect.stringContaining('Il corso è al completo.'));
  });

  it('se la rilettura del corso fallisce lo dice, ma l\'iscrizione era comunque riuscita', async () => {
    const el = await render(course());
    api.enroll.mockReturnValue(of(undefined));
    api.get.mockReturnValue(throwError(() => new HttpErrorResponse({ status: 500, error: null })));

    Array.from(el.querySelectorAll('button')).find((b) => b.textContent!.includes('Iscriviti'))!.click();
    fixture.detectChanges();

    expect(notify.success).toHaveBeenCalledWith('Iscrizione completata.');
    expect(notify.error).toHaveBeenCalledWith('Impossibile aggiornare i dati del corso.');
  });

  it('dopo l\'iscrizione i pulsanti tornano attivi quando il corso è stato riletto', async () => {
    const el = await render(course());
    api.enroll.mockReturnValue(of(undefined));
    api.get.mockReturnValue(of(course({ is_enrolled: true })));

    Array.from(el.querySelectorAll('button')).find((b) => b.textContent!.includes('Iscriviti'))!.click();
    fixture.detectChanges();

    expect(Array.from(el.querySelectorAll('button')).every((b) => !b.disabled)).toBe(true);
  });

  it('se il corso non esiste mostra un avviso', async () => {
    const el = await render(new Error('404'));

    expect(el.querySelector('[role="alert"]')).not.toBeNull();
  });

  it('è accessibile (WCAG 2.2 AA, senza il contrasto)', async () => {
    await expectNoA11yViolations(await render(course({ is_instructor: true })));
  });
});
