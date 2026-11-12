import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { expectNoA11yViolations } from '../../../testing/a11y';
import { Course } from '../../core/models/models';
import { CourseCardComponent } from './course-card.component';

/** Un corso di esempio, con la possibilità di cambiare qualche campo. */
function course(overrides: Partial<Course> = {}): Course {
  return {
    id: 7,
    name: 'Yoga',
    description: 'Posture e respirazione.',
    capacity: 12,
    day_of_week: 1,
    start_time: '18:30:00',
    duration_minutes: 60,
    is_active: true,
    bookable_id: null,
    instructors: [{ id: 2, name: 'Luca Verdi' }],
    enrolled_count: 4,
    spots_left: 8,
    is_enrolled: false,
    is_instructor: false,
    ...overrides,
  };
}

describe('CourseCardComponent', () => {
  let fixture: ComponentFixture<CourseCardComponent>;

  function render(c: Course): HTMLElement {
    fixture = TestBed.createComponent(CourseCardComponent);
    fixture.componentRef.setInput('course', c);
    fixture.detectChanges();
    return fixture.nativeElement as HTMLElement;
  }

  const badges = (el: HTMLElement) => Array.from(el.querySelectorAll('.badge')).map((b) => b.textContent!.trim());

  beforeEach(() => TestBed.configureTestingModule({ imports: [CourseCardComponent], providers: [provideRouter([])] }));

  it('mostra nome, quando si tiene e istruttore', () => {
    const el = render(course());

    expect(el.querySelector('h2')!.textContent).toContain('Yoga');
    expect(el.textContent).toContain('Lunedì dalle 18:30 alle 19:30');
    expect(el.textContent).toContain('Istruttore: Luca Verdi');
  });

  it('mostra la copertina: l\'immagine del corso se c\'è, altrimenti il ripiego', () => {
    const withImage = render(course({ image_url: '/api/courses/7/image?v=1' }));
    expect(withImage.querySelector('app-course-cover img')!.getAttribute('src')).toBe('/api/courses/7/image?v=1');

    const without = render(course({ image_url: null }));
    expect(without.querySelector('app-course-cover .cover-fallback')).not.toBeNull();
  });

  it('scrive i posti liberi a parole', () => {
    expect(badges(render(course({ spots_left: 8 })))).toContain('8 posti liberi');
  });

  it('usa il singolare per un solo posto', () => {
    expect(badges(render(course({ spots_left: 1 })))).toContain('1 posto libero');
  });

  it('segnala "Al completo" a parole, non solo con il colore', () => {
    const el = render(course({ spots_left: 0 }));

    expect(badges(el)).toContain('Al completo');
    expect(el.querySelector('.badge--warn')!.textContent).toContain('Al completo');
  });

  it('segnala "Sei iscritto" e "Sei istruttore di questo corso"', () => {
    expect(badges(render(course({ is_enrolled: true })))).toContain('Sei iscritto');
    expect(badges(render(course({ is_instructor: true })))).toContain('Sei istruttore di questo corso');
  });

  it('segnala un corso disattivato', () => {
    expect(badges(render(course({ is_active: false })))).toContain('Disattivato');
  });

  it('non inventa i posti se il dato manca', () => {
    expect(badges(render(course({ spots_left: undefined })))).toEqual([]);
  });

  it('il collegamento ai dettagli ha un nome che include il corso', () => {
    const link = render(course()).querySelector('a')!;

    expect(link.getAttribute('aria-label')).toBe('Dettagli del corso Yoga');
    expect(link.getAttribute('href')).toBe('/courses/7');
  });

  it('può nascondere il collegamento ai dettagli', () => {
    fixture = TestBed.createComponent(CourseCardComponent);
    fixture.componentRef.setInput('course', course());
    fixture.componentRef.setInput('showDetailLink', false);
    fixture.detectChanges();

    expect((fixture.nativeElement as HTMLElement).querySelector('a')).toBeNull();
  });

  it('è accessibile (WCAG 2.2 AA, senza il contrasto)', async () => {
    await expectNoA11yViolations(render(course({ is_enrolled: true, spots_left: 0 })));
  });
});
