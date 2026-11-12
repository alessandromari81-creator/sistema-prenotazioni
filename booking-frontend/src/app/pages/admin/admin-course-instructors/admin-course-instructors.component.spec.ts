import { TestBed } from '@angular/core/testing';
import { HttpErrorResponse } from '@angular/common/http';
import { ActivatedRoute, convertToParamMap, provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { expectNoA11yViolations } from '../../../../testing/a11y';
import { Course } from '../../../core/models/models';
import { CourseService } from '../../../core/services/http/course.service';
import { UserService } from '../../../core/services/http/user.service';
import { AdminCourseInstructorsComponent } from './admin-course-instructors.component';

const baseCourse: Course = {
  id: 7, name: 'Yoga', description: null, capacity: 12, day_of_week: 1, start_time: '18:30:00', duration_minutes: 60,
  is_active: true, bookable_id: null, instructors: [{ id: 2, name: 'Luca Verdi' }],
};

const users = { data: [{ id: 4, name: 'Mario Rossi', email: 'mario@example.com', role: 'user' }, { id: 2, name: 'Luca Verdi', email: 'luca@example.com', role: 'user' }] };

describe('AdminCourseInstructorsComponent', () => {
  let courseApi: { get: ReturnType<typeof vi.fn>; addInstructor: ReturnType<typeof vi.fn>; removeInstructor: ReturnType<typeof vi.fn> };

  async function render() {
    courseApi = { get: vi.fn().mockReturnValue(of(baseCourse)), addInstructor: vi.fn(), removeInstructor: vi.fn() };

    await TestBed.configureTestingModule({
      imports: [AdminCourseInstructorsComponent],
      providers: [
        provideRouter([]),
        { provide: CourseService, useValue: courseApi },
        { provide: UserService, useValue: { list: vi.fn().mockReturnValue(of(users)) } },
        { provide: ActivatedRoute, useValue: { snapshot: { paramMap: convertToParamMap({ id: '7' }) } } },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(AdminCourseInstructorsComponent);
    fixture.detectChanges();
    return { el: fixture.nativeElement as HTMLElement, fixture };
  }

  async function search(el: HTMLElement, fixture: { detectChanges(): void }) {
    el.querySelector<HTMLFormElement>('form[role="search"]')!.dispatchEvent(new Event('submit'));
    fixture.detectChanges();
  }

  const text = (el: HTMLElement) => el.textContent!.replace(/\s+/g, ' ');

  it('mostra gli istruttori attuali del corso', async () => {
    const { el } = await render();

    expect(el.querySelector('h1')!.textContent).toContain('Istruttori di Yoga');
    expect(text(el)).toContain('Luca Verdi');
    expect(el.querySelector('button[aria-label="Togli Luca Verdi dagli istruttori"]')).not.toBeNull();
  });

  it('la ricerca mostra gli utenti e segna chi è già istruttore', async () => {
    const { el, fixture } = await render();

    await search(el, fixture);

    expect(el.querySelector('button[aria-label="Assegna Mario Rossi come istruttore"]')).not.toBeNull();
    expect(text(el)).toContain('Già istruttore');
    expect(el.querySelector('button[aria-label="Assegna Luca Verdi come istruttore"]')).toBeNull();
  });

  it('assegnando un utente aggiorna l\'elenco e annuncia l\'esito', async () => {
    const { el, fixture } = await render();
    courseApi.addInstructor.mockReturnValue(of({ ...baseCourse, instructors: [...baseCourse.instructors!, { id: 4, name: 'Mario Rossi' }] }));
    await search(el, fixture);

    el.querySelector<HTMLButtonElement>('button[aria-label="Assegna Mario Rossi come istruttore"]')!.click();
    fixture.detectChanges();

    expect(courseApi.addInstructor).toHaveBeenCalledWith(7, 4);
    expect(el.querySelector('.alert[role="status"]')!.textContent).toContain('Mario Rossi è ora istruttore del corso.');
    expect(text(el)).toContain('Già istruttore');
  });

  it('se l\'utente è iscritto al corso come studente mostra il rifiuto del server', async () => {
    const { el, fixture } = await render();
    courseApi.addInstructor.mockReturnValue(throwError(() => new HttpErrorResponse({
      status: 422,
      error: { errors: { user_id: ['Questo utente è iscritto al corso come studente: non può esserne anche istruttore.'] } },
    })));
    await search(el, fixture);

    el.querySelector<HTMLButtonElement>('button[aria-label="Assegna Mario Rossi come istruttore"]')!.click();
    fixture.detectChanges();

    expect(el.querySelector('.alert--error[role="alert"]')!.textContent).toContain('iscritto al corso come studente');
  });

  it('togliendo un istruttore aggiorna l\'elenco', async () => {
    const { el, fixture } = await render();
    courseApi.removeInstructor.mockReturnValue(of({ ...baseCourse, instructors: [] }));

    el.querySelector<HTMLButtonElement>('button[aria-label="Togli Luca Verdi dagli istruttori"]')!.click();
    fixture.detectChanges();

    expect(courseApi.removeInstructor).toHaveBeenCalledWith(7, 2);
    expect(text(el)).toContain('Questo corso non ha ancora istruttori.');
  });

  it('è accessibile (WCAG 2.2 AA, senza il contrasto)', async () => {
    const { el, fixture } = await render();
    await search(el, fixture);

    await expectNoA11yViolations(el);
  });
});
