import { TestBed } from '@angular/core/testing';
import { HttpErrorResponse } from '@angular/common/http';
import { ActivatedRoute, convertToParamMap, Router, provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { expectNoA11yViolations } from '../../../../testing/a11y';
import { BookingService } from '../../../core/services/http/booking.service';
import { CourseService } from '../../../core/services/http/course.service';
import { AdminCourseFormComponent } from './admin-course-form.component';

describe('AdminCourseFormComponent', () => {
  let api: { create: ReturnType<typeof vi.fn>; update: ReturnType<typeof vi.fn>; get: ReturnType<typeof vi.fn> };

  async function render(id: string | null = null) {
    api = { create: vi.fn().mockReturnValue(of({})), update: vi.fn().mockReturnValue(of({})), get: vi.fn() };
    api.get.mockReturnValue(of({
      id: 3, name: 'Pilates', description: null, capacity: 10, day_of_week: 3, start_time: '19:00:00', duration_minutes: 55,
      is_active: false, bookable_id: null,
    }));

    await TestBed.configureTestingModule({
      imports: [AdminCourseFormComponent],
      providers: [
        provideRouter([]),
        { provide: CourseService, useValue: api },
        { provide: BookingService, useValue: { getBookables: vi.fn().mockReturnValue(of({ data: [{ id: 1, name: 'Sala grande' }] })) } },
        { provide: ActivatedRoute, useValue: { snapshot: { paramMap: convertToParamMap(id ? { id } : {}) } } },
      ],
    }).compileComponents();

    const navigate = vi.spyOn(TestBed.inject(Router), 'navigate').mockResolvedValue(true);
    const fixture = TestBed.createComponent(AdminCourseFormComponent);
    fixture.detectChanges();
    return { el: fixture.nativeElement as HTMLElement, fixture, navigate };
  }

  const input = (el: HTMLElement, name: string) => el.querySelector<HTMLInputElement>(`[formControlName="${name}"]`)!;
  const type = (el: HTMLElement, name: string, value: string) => {
    const field = input(el, name);
    field.value = value;
    field.dispatchEvent(new Event('input'));
  };
  const submit = (el: HTMLElement) => el.querySelector<HTMLButtonElement>('button[type="submit"]')!.click();

  it('in creazione parte con valori predefiniti ragionevoli', async () => {
    const { el } = await render();

    expect(el.querySelector('h1')!.textContent).toBe('Nuovo corso');
    expect(input(el, 'capacity').value).toBe('12');
    expect(input(el, 'start_time').value).toBe('18:00');
    expect(input(el, 'duration_minutes').value).toBe('60');
  });

  it('senza il nome non invia nulla e mostra il motivo', async () => {
    const { el, fixture } = await render();

    submit(el);
    fixture.detectChanges();

    expect(api.create).not.toHaveBeenCalled();
    expect(el.textContent).toContain('Il nome è obbligatorio');
  });

  it('con dati validi crea il corso e torna all\'elenco', async () => {
    const { el, fixture, navigate } = await render();

    type(el, 'name', 'Yoga');
    type(el, 'capacity', '15');
    fixture.detectChanges();
    submit(el);

    expect(api.create).toHaveBeenCalledWith(expect.objectContaining({ name: 'Yoga', capacity: 15, description: null, bookable_id: null, is_active: true }));
    expect(navigate).toHaveBeenCalledWith(['/admin/courses']);
  });

  it('non accetta posti fuori dall\'intervallo del server (1-200)', async () => {
    const { el, fixture } = await render();

    type(el, 'name', 'Yoga');
    type(el, 'capacity', '500');
    fixture.detectChanges();
    submit(el);
    fixture.detectChanges();

    expect(api.create).not.toHaveBeenCalled();
    expect(el.textContent).toContain('Indica un numero da 1 a 200');
  });

  it('in modifica carica il corso e porta l\'orario a HH:MM', async () => {
    const { el } = await render('3');

    expect(el.querySelector('h1')!.textContent).toBe('Modifica corso');
    expect(api.get).toHaveBeenCalledWith(3);
    expect(input(el, 'name').value).toBe('Pilates');
    expect(input(el, 'start_time').value).toBe('19:00');
  });

  it('in modifica salva con update', async () => {
    const { el, fixture } = await render('3');

    submit(el);
    fixture.detectChanges();

    expect(api.update).toHaveBeenCalledWith(3, expect.objectContaining({ name: 'Pilates', is_active: false }));
  });

  it('mostra l\'errore del server (per esempio posti sotto gli iscritti) e resta sulla pagina', async () => {
    const { el, fixture, navigate } = await render('3');
    api.update.mockReturnValue(throwError(() => new HttpErrorResponse({ status: 422, error: { errors: { capacity: ['I posti non possono essere meno degli iscritti attuali (3).'] } } })));

    submit(el);
    fixture.detectChanges();

    expect(el.querySelector('.alert--error[role="alert"]')!.textContent).toContain('I posti non possono essere meno degli iscritti attuali (3).');
    expect(navigate).not.toHaveBeenCalled();
  });

  it('è accessibile (WCAG 2.2 AA, senza il contrasto)', async () => {
    const { el } = await render();

    await expectNoA11yViolations(el);
  });
});
