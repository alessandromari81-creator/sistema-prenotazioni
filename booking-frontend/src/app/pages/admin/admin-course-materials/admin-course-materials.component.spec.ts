import { TestBed } from '@angular/core/testing';
import { HttpErrorResponse } from '@angular/common/http';
import { ActivatedRoute, convertToParamMap, provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { expectNoA11yViolations } from '../../../../testing/a11y';
import { FileSaverService } from '../../../core/services/features/file-saver.service';
import { ConfirmService } from '../../../core/services/features/confirm.service';
import { NotificationService } from '../../../core/services/features/notification.service';
import { FakeConfirm } from '../../../../testing/confirm';
import { CourseService } from '../../../core/services/http/course.service';
import { AdminCourseMaterialsComponent } from './admin-course-materials.component';

const course = { id: 7, name: 'Yoga', description: null, capacity: 12, day_of_week: 1, start_time: '18:30:00', duration_minutes: 60, is_active: true, bookable_id: null };
const material = { id: 5, course_id: 7, title: 'Scheda di allenamento', size_bytes: 120 * 1024, created_at: null };

describe('AdminCourseMaterialsComponent', () => {
  const notify = { success: vi.fn(), error: vi.fn() };
  const confirmer = new FakeConfirm(); // la finestra di conferma: ogni test decide se l'utente conferma
  let api: Record<'get' | 'materials' | 'uploadMaterial' | 'deleteMaterial' | 'materialFile', ReturnType<typeof vi.fn>>;
  const fileSaver = { show: vi.fn(), save: vi.fn() };

  beforeEach(() => {
    notify.success.mockReset();
    notify.error.mockReset();
    fileSaver.show.mockReset();
    fileSaver.save.mockReset();
  });

  async function render(list = [material]) {
    api = {
      get: vi.fn().mockReturnValue(of(course)),
      materials: vi.fn().mockReturnValue(of(list)),
      uploadMaterial: vi.fn().mockReturnValue(of({ ...material, id: 6, title: 'Dispensa' })),
      deleteMaterial: vi.fn().mockReturnValue(of(undefined)),
      materialFile: vi.fn().mockReturnValue(of(new Blob(['%PDF']))),
    };

    await TestBed.configureTestingModule({
      imports: [AdminCourseMaterialsComponent],
      providers: [
        provideRouter([]),
        { provide: NotificationService, useValue: notify }, { provide: ConfirmService, useValue: confirmer },
        { provide: FileSaverService, useValue: fileSaver },
        { provide: CourseService, useValue: api },
        { provide: ActivatedRoute, useValue: { snapshot: { paramMap: convertToParamMap({ id: '7' }) } } },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(AdminCourseMaterialsComponent);
    fixture.detectChanges();
    return { el: fixture.nativeElement as HTMLElement, fixture };
  }

  const text = (el: HTMLElement) => el.textContent!.replace(/\s+/g, ' ');
  const fileInput = (el: HTMLElement) => el.querySelector<HTMLInputElement>('input[type="file"]')!;
  const button = (el: HTMLElement, label: string) => Array.from(el.querySelectorAll('button')).find((b) => b.textContent!.includes(label))!;

  function setTitle(el: HTMLElement, value: string): void {
    const field = el.querySelector<HTMLInputElement>('input[name="title"]')!;
    field.value = value;
    field.dispatchEvent(new Event('input'));
  }
  function chooseFile(el: HTMLElement, file: File): void {
    Object.defineProperty(fileInput(el), 'files', { value: [file], configurable: true });
    fileInput(el).dispatchEvent(new Event('change'));
  }
  const pdf = (size = 1000) => new File([new Uint8Array(size)], 'dispensa.pdf', { type: 'application/pdf' });

  it('mostra il corso e i materiali caricati', async () => {
    const { el } = await render();

    expect(el.querySelector('h1')!.textContent).toBe('Materiali di Yoga');
    expect(api.materials).toHaveBeenCalledWith(7);
    expect(text(el)).toContain('Scheda di allenamento');
    expect(text(el)).toContain('PDF, 120 KB');
  });

  it('senza materiali lo scrive a parole', async () => {
    const { el } = await render([]);

    expect(text(el)).toContain('Questo corso non ha ancora materiali.');
  });

  it('il campo file è nascosto: l\'unico controllo raggiungibile è il pulsante', async () => {
    const { el } = await render();

    expect(fileInput(el).hidden).toBe(true);
    expect(button(el, 'Scegli un PDF')).toBeTruthy();
  });

  it('rifiuta subito un file che non è un PDF', async () => {
    const { el, fixture } = await render();

    chooseFile(el, new File(['x'], 'foto.png', { type: 'image/png' }));
    fixture.detectChanges();

    expect(el.querySelector('#pdf-error')!.textContent).toContain('Il file deve essere un PDF.');
  });

  it('rifiuta subito un PDF sopra i 10 MB', async () => {
    const { el, fixture } = await render();

    chooseFile(el, pdf(10 * 1024 * 1024 + 1));
    fixture.detectChanges();

    expect(el.querySelector('#pdf-error')!.textContent).toContain('10 MB');
  });

  it('senza titolo o senza file non invia nulla e lo dice', async () => {
    const { el, fixture } = await render();

    el.querySelector('form')!.dispatchEvent(new Event('submit'));
    expect(notify.error).toHaveBeenCalledWith('Scrivi un titolo per il materiale.');

    setTitle(el, 'Dispensa');
    fixture.detectChanges();
    el.querySelector('form')!.dispatchEvent(new Event('submit'));
    expect(notify.error).toHaveBeenCalledWith('Scegli un file PDF da caricare.');
    expect(api.uploadMaterial).not.toHaveBeenCalled();
  });

  it('carica titolo e file, aggiunge il materiale all\'elenco e svuota il form', async () => {
    const { el, fixture } = await render();
    const file = pdf();

    setTitle(el, '  Dispensa  ');
    chooseFile(el, file);
    fixture.detectChanges();
    el.querySelector('form')!.dispatchEvent(new Event('submit'));
    fixture.detectChanges();

    expect(api.uploadMaterial).toHaveBeenCalledWith(7, 'Dispensa', file);
    expect(notify.success).toHaveBeenCalledWith('Materiale "Dispensa" caricato.');
    expect(text(el)).toContain('Dispensa');
    expect(el.querySelector<HTMLInputElement>('input[name="title"]')!.value).toBe('');
  });

  it('mostra l\'errore del server se il caricamento fallisce e non aggiunge nulla', async () => {
    const { el, fixture } = await render();
    api.uploadMaterial.mockReturnValue(throwError(() => new HttpErrorResponse({ status: 422, error: { errors: { file: ['Il file deve essere un PDF.'] } } })));

    setTitle(el, 'Dispensa');
    chooseFile(el, pdf());
    fixture.detectChanges();
    el.querySelector('form')!.dispatchEvent(new Event('submit'));
    fixture.detectChanges();

    expect(notify.error).toHaveBeenCalledWith('Il file deve essere un PDF.');
    expect(el.querySelectorAll('.plain-list li').length).toBe(1);
  });

  it('elimina un materiale dopo la conferma', async () => {
    const { el, fixture } = await render();
    confirmer.answer = true;

    button(el, 'Elimina').click();
    fixture.detectChanges();

    expect(api.deleteMaterial).toHaveBeenCalledWith(7, 5);
    expect(notify.success).toHaveBeenCalledWith('Materiale "Scheda di allenamento" eliminato.');
    expect(text(el)).toContain('Questo corso non ha ancora materiali.');
  });

  it('senza conferma non elimina nulla', async () => {
    const { el } = await render();
    confirmer.answer = false;

    button(el, 'Elimina').click();

    expect(api.deleteMaterial).not.toHaveBeenCalled();
  });

  it('"Visualizza" apre subito una scheda e la riempie con il PDF chiesto all\'API con il token', async () => {
    const tab = { close: vi.fn() } as unknown as Window;
    const open = vi.spyOn(window, 'open').mockReturnValue(tab);
    const { el, fixture } = await render();

    button(el, 'Visualizza').click();
    fixture.detectChanges();

    expect(open).toHaveBeenCalledWith('', '_blank');
    expect(api.materialFile).toHaveBeenCalledWith(7, 5);
    expect(fileSaver.show).toHaveBeenCalledWith(expect.any(Blob), tab, 'Scheda di allenamento.pdf');
    open.mockRestore();
  });

  it('"Visualizza": se il server rifiuta chiude la scheda e avvisa', async () => {
    const tab = { close: vi.fn() } as unknown as Window;
    const open = vi.spyOn(window, 'open').mockReturnValue(tab);
    const { el, fixture } = await render();
    api.materialFile.mockReturnValue(throwError(() => new HttpErrorResponse({ status: 500 })));

    button(el, 'Visualizza').click();
    fixture.detectChanges();

    expect(tab.close).toHaveBeenCalled();
    expect(notify.error).toHaveBeenCalledWith('Impossibile aprire il file.');
    expect(fileSaver.show).not.toHaveBeenCalled();
    open.mockRestore();
  });

  it('"Scarica" salva il file con il titolo', async () => {
    const { el, fixture } = await render();

    button(el, 'Scarica').click();
    fixture.detectChanges();

    expect(api.materialFile).toHaveBeenCalledWith(7, 5);
    expect(fileSaver.save).toHaveBeenCalledWith(expect.any(Blob), 'Scheda di allenamento.pdf');
  });

  it('è accessibile (WCAG 2.2 AA, senza il contrasto), anche con un errore sul file', async () => {
    const { el, fixture } = await render();
    chooseFile(el, new File(['x'], 'foto.png', { type: 'image/png' }));
    fixture.detectChanges();

    await expectNoA11yViolations(el);
  });
});
