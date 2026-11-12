import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { expectNoA11yViolations } from '../../../../testing/a11y';
import { Course, SubscriptionUsage } from '../../../core/models/models';
import { CourseService } from '../../../core/services/http/course.service';
import { SubscriptionService } from '../../../core/services/http/subscription.service';
import { MyCoursesComponent } from './my-courses.component';

const yoga: Course = {
  id: 7, name: 'Yoga', description: null, capacity: 12, day_of_week: 1, start_time: '18:30:00', duration_minutes: 60,
  is_active: true, bookable_id: null, instructors: [], enrolled_count: 1, spots_left: 11, is_enrolled: true, is_instructor: false,
};

const withPlan: SubscriptionUsage = {
  subscription: {
    id: 1, user_id: 4, subscription_plan_id: 1, starts_on: '2026-11-01', ends_on: '2026-11-30', cancelled_at: null, is_active: true,
    plan: { id: 1, name: 'Mensile', description: null, duration_days: 30, max_courses: 2, is_active: true },
  },
  courses_used: 1,
  courses_left: 1,
};

describe('MyCoursesComponent', () => {
  async function render(courses: Course[], usage: SubscriptionUsage) {
    const courseApi = { myCourses: vi.fn().mockReturnValue(of(courses)), cancelEnrollment: vi.fn().mockReturnValue(of({})) };
    const subscriptionApi = { mine: vi.fn().mockReturnValue(of(usage)) };

    await TestBed.configureTestingModule({
      imports: [MyCoursesComponent],
      providers: [provideRouter([]), { provide: CourseService, useValue: courseApi }, { provide: SubscriptionService, useValue: subscriptionApi }],
    }).compileComponents();

    const fixture = TestBed.createComponent(MyCoursesComponent);
    fixture.detectChanges();
    return { el: fixture.nativeElement as HTMLElement, fixture, courseApi };
  }

  const text = (el: HTMLElement) => el.textContent!.replace(/\s+/g, ' ');

  it('riassume l\'abbonamento con date italiane e corsi disponibili', async () => {
    const { el } = await render([yoga], withPlan);

    expect(text(el)).toContain('Mensile');
    expect(text(el)).toContain('dal 01/11/2026 al 30/11/2026');
    expect(text(el)).toContain('Stai seguendo 1 corso su 2: ne puoi ancora seguire 1.');
  });

  it('senza abbonamento spiega come ottenerne uno', async () => {
    const { el } = await render([], { subscription: null, courses_used: 0, courses_left: 0 });

    expect(text(el)).toContain('Non hai un abbonamento attivo');
  });

  it('elenca i corsi a cui sei iscritto', async () => {
    const { el } = await render([yoga], withPlan);

    expect(el.querySelectorAll('app-course-card')).toHaveLength(1);
    expect(el.querySelector('button[aria-label="Annulla iscrizione a Yoga"]')).not.toBeNull();
  });

  it('invita a scoprire i corsi se non sei iscritto a nessuno', async () => {
    const { el } = await render([], withPlan);

    expect(text(el)).toContain('Non sei iscritto a nessun corso.');
    expect(el.querySelector('a[href="/courses"]')).not.toBeNull();
  });

  it('annullando un\'iscrizione chiama l\'API e annuncia l\'esito', async () => {
    const { el, fixture, courseApi } = await render([yoga], withPlan);

    el.querySelector<HTMLButtonElement>('button[aria-label="Annulla iscrizione a Yoga"]')!.click();
    fixture.detectChanges();

    expect(courseApi.cancelEnrollment).toHaveBeenCalledWith(7);
    expect(el.querySelector('.alert[role="status"]')!.textContent).toContain('Iscrizione al corso Yoga annullata.');
  });

  it('è accessibile (WCAG 2.2 AA, senza il contrasto)', async () => {
    const { el } = await render([yoga], withPlan);

    await expectNoA11yViolations(el);
  });
});
