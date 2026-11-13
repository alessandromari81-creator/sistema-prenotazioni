import { TestBed } from '@angular/core/testing';
import { HttpErrorResponse } from '@angular/common/http';
import { ActivatedRoute, convertToParamMap, Router, provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { expectNoA11yViolations } from '../../../../testing/a11y';
import { BookingService } from '../../../core/services/http/booking.service';
import { CourseService } from '../../../core/services/http/course.service';
import { AdminCourseFormComponent } from './admin-course-form.component';
import { ConfirmService } from '../../../core/services/features/confirm.service';
import { NotificationService } from '../../../core/services/features/notification.service';
import { FakeConfirm } from '../../../../testing/confirm';

describe('AdminCourseFormComponent', () => {
  // Gli avvisi a comparsa (snackbar) si provano sul servizio: qui non c'è nessun avviso fisso nella pagina
  const notify = { success: vi.fn(), error: vi.fn() };
  const confirmer = new FakeConfirm(); // la finestra di conferma: ogni test decide se l'utente conferma
  beforeEach(() => {
    notify.success.mockReset();
    notify.error.mockReset();
  });

  let api: {
    create: ReturnType<typeof vi.fn>; update: ReturnType<typeof vi.fn>; get: ReturnType<typeof vi.fn>;
    uploadImage: ReturnType<typeof vi.fn>; removeImage: ReturnType<typeof vi.fn>;
  };

  async function render(id: string | null = null) {
    api = {
      create: vi.fn().mockReturnValue(of({ id: 9 })), update: vi.fn().mockReturnValue(of({ id: 3 })), get: vi.fn(),
      uploadImage: vi.fn().mockReturnValue(of({ id: 9 })), removeImage: vi.fn().mockReturnValue(of(undefined)),
    };
    api.get.mockReturnValue(of({
      id: 3, name: 'Pilates', description: null, capacity: 10, day_of_week: 3, start_time: '19:00:00', duration_minutes: 55,
      is_active: false, bookable_id: null,
    }));

    await TestBed.configureTestingModule({
      imports: [AdminCourseFormComponent],
      providers: [{ provide: NotificationService, useValue: notify }, { provide: ConfirmService, useValue: confirmer }, 
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

    expect(notify.error).toHaveBeenCalledWith(expect.stringContaining('I posti non possono essere meno degli iscritti attuali (3).'));
    expect(navigate).not.toHaveBeenCalled();
  });

  // ---------- immagine del corso ----------

  /** Simula la scelta di un file nel campo (nascosto) dell'immagine. */
  function chooseFile(el: HTMLElement, file: File): void {
    const field = el.querySelector<HTMLInputElement>('input[type="file"]')!;
    Object.defineProperty(field, 'files', { value: [file], configurable: true });
    field.dispatchEvent(new Event('change'));
  }
  const png = (size = 1000) => new File([new Uint8Array(size)], 'foto.png', { type: 'image/png' });

  beforeEach(() => {
    // jsdom non sa creare gli indirizzi temporanei delle anteprime
    URL.createObjectURL = vi.fn().mockReturnValue('blob:anteprima');
    URL.revokeObjectURL = vi.fn();
  });

  it('il campo file è nascosto: l\'unico controllo raggiungibile è il pulsante', async () => {
    const { el } = await render();

    expect(el.querySelector<HTMLInputElement>('input[type="file"]')!.hidden).toBe(true);
    expect(el.textContent).toContain('Scegli un\'immagine');
  });

  it('rifiuta subito un formato non ammesso e non lo tiene da parte', async () => {
    const { el, fixture } = await render();

    chooseFile(el, new File(['x'], 'foto.gif', { type: 'image/gif' }));
    fixture.detectChanges();

    expect(el.querySelector('#course-image-error')!.textContent).toContain('JPEG, PNG o WebP');
    expect(el.querySelector('img[alt^="Anteprima"]')).toBeNull();
  });

  it('rifiuta subito un file sopra i 2 MB', async () => {
    const { el, fixture } = await render();

    chooseFile(el, png(2 * 1024 * 1024 + 1));
    fixture.detectChanges();

    expect(el.querySelector('#course-image-error')!.textContent).toContain('2 MB');
  });

  it('un file valido mostra l\'anteprima e dice che verrà caricato al salvataggio', async () => {
    const { el, fixture } = await render();

    chooseFile(el, png());
    fixture.detectChanges();

    expect(el.querySelector('img[alt^="Anteprima"]')!.getAttribute('src')).toBe('blob:anteprima');
    expect(el.querySelector('[role="status"]')!.textContent).toContain('foto.png');
    expect(api.uploadImage).not.toHaveBeenCalled();
  });

  it('salvando un corso nuovo con un file scelto, prima crea il corso e poi carica l\'immagine', async () => {
    const { el, fixture, navigate } = await render();
    const file = png();

    type(el, 'name', 'Yoga');
    chooseFile(el, file);
    fixture.detectChanges();
    submit(el);

    expect(api.create).toHaveBeenCalled();
    expect(api.uploadImage).toHaveBeenCalledWith(9, file);
    expect(navigate).toHaveBeenCalledWith(['/admin/courses']);
  });

  it('senza file scelto non chiama il caricamento', async () => {
    const { el, fixture } = await render('3');

    submit(el);
    fixture.detectChanges();

    expect(api.uploadImage).not.toHaveBeenCalled();
  });

  it('se solo l\'immagine fallisce il corso resta salvato: si avvisa e si torna all\'elenco', async () => {
    const { el, fixture, navigate } = await render();
    api.uploadImage.mockReturnValue(throwError(() => new HttpErrorResponse({ status: 422, error: { errors: { image: ['L\'immagine non può superare i 2 MB.'] } } })));

    type(el, 'name', 'Yoga');
    chooseFile(el, png());
    fixture.detectChanges();
    submit(el);

    expect(notify.error).toHaveBeenCalledWith(expect.stringContaining('2 MB'));
    expect(navigate).toHaveBeenCalledWith(['/admin/courses']);
  });

  it('"Non caricare" scarta il file scelto', async () => {
    const { el, fixture } = await render();

    chooseFile(el, png());
    fixture.detectChanges();
    Array.from(el.querySelectorAll('button')).find((b) => b.textContent!.includes('Non caricare'))!.click();
    fixture.detectChanges();

    expect(el.querySelector('img[alt^="Anteprima"]')).toBeNull();
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:anteprima');
  });

  it('in modifica, "Togli immagine" chiede conferma e poi toglie l\'immagine dal corso', async () => {
    const { el, fixture } = await render('3');
    api.get.mockReturnValue(of({ id: 3, name: 'Pilates', description: null, image_url: '/api/courses/3/image?v=1', capacity: 10, day_of_week: 3, start_time: '19:00:00', duration_minutes: 55, is_active: true, bookable_id: null }));
    confirmer.answer = true;
    // Si ricrea il componente perché il corso con immagine arrivi al caricamento
    const again = TestBed.createComponent(AdminCourseFormComponent);
    again.detectChanges();
    const remove = Array.from((again.nativeElement as HTMLElement).querySelectorAll('button')).find((b) => b.textContent!.includes('Togli immagine'))!;

    remove.click();
    again.detectChanges();

    expect(confirmer.confirm).toHaveBeenCalled();
    expect(api.removeImage).toHaveBeenCalledWith(3);
    expect(notify.success).toHaveBeenCalledWith('Immagine del corso tolta.');
    expect(Array.from((again.nativeElement as HTMLElement).querySelectorAll('button')).some((b) => b.textContent!.includes('Togli immagine'))).toBe(false);
    void el; void fixture;
  });

  it('se non si conferma, l\'immagine non viene tolta', async () => {
    await render('3');
    api.get.mockReturnValue(of({ id: 3, name: 'Pilates', description: null, image_url: '/x', capacity: 10, day_of_week: 3, start_time: '19:00:00', duration_minutes: 55, is_active: true, bookable_id: null }));
    confirmer.answer = false;
    const again = TestBed.createComponent(AdminCourseFormComponent);
    again.detectChanges();

    Array.from((again.nativeElement as HTMLElement).querySelectorAll('button')).find((b) => b.textContent!.includes('Togli immagine'))!.click();

    expect(api.removeImage).not.toHaveBeenCalled();
  });

  it('con un\'immagine scelta e l\'errore mostrato la pagina resta accessibile', async () => {
    const { el, fixture } = await render();

    chooseFile(el, new File(['x'], 'foto.gif', { type: 'image/gif' }));
    fixture.detectChanges();

    await expectNoA11yViolations(el);
  });

  it('in modifica offre il collegamento ai materiali PDF del corso; in creazione no', async () => {
    const edit = await render('3');
    const link = Array.from(edit.el.querySelectorAll('a')).find((a) => a.textContent!.includes('Gestisci i materiali PDF'));
    expect(link!.getAttribute('href')).toBe('/admin/courses/3/materials');
    TestBed.resetTestingModule();

    const create = await render();
    expect(create.el.textContent).not.toContain('Gestisci i materiali PDF');
  });

  it('è accessibile (WCAG 2.2 AA, senza il contrasto)', async () => {
    const { el } = await render();

    await expectNoA11yViolations(el);
  });
});
