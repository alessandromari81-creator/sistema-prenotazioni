import { ComponentFixture, TestBed } from '@angular/core/testing';
import { expectNoA11yViolations } from '../../../testing/a11y';
import { CourseCoverComponent } from './course-cover.component';

describe('CourseCoverComponent', () => {
  let fixture: ComponentFixture<CourseCoverComponent>;

  function render(course: { id: number; image_url?: string | null }): HTMLElement {
    fixture = TestBed.createComponent(CourseCoverComponent);
    fixture.componentRef.setInput('course', course);
    fixture.detectChanges();
    return fixture.nativeElement as HTMLElement;
  }

  beforeEach(() => TestBed.configureTestingModule({ imports: [CourseCoverComponent] }));

  it('mostra l\'immagine caricata, decorativa (alt vuoto) perché il nome sta già scritto accanto', () => {
    const el = render({ id: 1, image_url: '/api/courses/1/image?v=5' });
    const img = el.querySelector('img')!;

    expect(img.getAttribute('src')).toBe('/api/courses/1/image?v=5');
    expect(img.getAttribute('alt')).toBe('');
    expect(el.querySelector('.cover-fallback')).toBeNull();
  });

  it('senza immagine disegna il ripiego, nascosto ai lettori di schermo', () => {
    const el = render({ id: 1, image_url: null });

    expect(el.querySelector('img')).toBeNull();
    expect(el.querySelector('.cover-fallback')!.getAttribute('aria-hidden')).toBe('true');
  });

  it('un corso senza il campo image_url (risposta vecchia) usa il ripiego', () => {
    expect(render({ id: 2 }).querySelector('.cover-fallback')).not.toBeNull();
  });

  it('lo stesso corso ha sempre la stessa icona e sfumatura; corsi diversi possono averle diverse', () => {
    const a = render({ id: 3 });
    const iconA = a.querySelector('mat-icon')!.textContent;
    const toneA = a.querySelector('.cover-fallback')!.className;

    const again = render({ id: 3 });
    const other = render({ id: 4 });

    expect(again.querySelector('mat-icon')!.textContent).toBe(iconA);
    expect(again.querySelector('.cover-fallback')!.className).toBe(toneA);
    expect(other.querySelector('.cover-fallback')!.className).not.toBe(toneA);
  });

  it('è accessibile con immagine e con il ripiego', async () => {
    await expectNoA11yViolations(render({ id: 1, image_url: '/x' }));
    await expectNoA11yViolations(render({ id: 2, image_url: null }));
  });
});
