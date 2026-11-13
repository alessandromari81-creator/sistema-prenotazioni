import { TestBed } from '@angular/core/testing';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { vi } from 'vitest';
import { expectNoA11yViolations } from '../../../testing/a11y';
import { ConfirmDialogComponent, ConfirmDialogData } from './confirm-dialog.component';

describe('ConfirmDialogComponent', () => {
  async function render(data: ConfirmDialogData) {
    await TestBed.configureTestingModule({
      imports: [ConfirmDialogComponent],
      providers: [{ provide: MAT_DIALOG_DATA, useValue: data }, { provide: MatDialogRef, useValue: { close: vi.fn() } }],
    }).compileComponents();

    const fixture = TestBed.createComponent(ConfirmDialogComponent);
    fixture.detectChanges();
    return fixture.nativeElement as HTMLElement;
  }

  const data = { title: 'Annullare l\'abbonamento?', message: 'Le iscrizioni restano valide.', confirmLabel: 'Annulla abbonamento' };
  const buttons = (el: HTMLElement) => Array.from(el.querySelectorAll('button')).map((b) => b.textContent!.trim());

  it('mostra titolo, messaggio e i due pulsanti', async () => {
    const el = await render(data);

    expect(el.querySelector('h2')!.textContent).toContain('Annullare l\'abbonamento?');
    expect(el.textContent).toContain('Le iscrizioni restano valide.');
    expect(buttons(el)).toEqual(['Annulla', 'Annulla abbonamento']);
  });

  it('il pulsante che rinuncia ha un testo scelto da chi chiama', async () => {
    const el = await render({ ...data, cancelLabel: 'Mantieni' });

    expect(buttons(el)).toEqual(['Mantieni', 'Annulla abbonamento']);
  });

  it('il focus iniziale è sul pulsante che rinuncia, non su quello distruttivo', async () => {
    const el = await render(data);

    expect(el.querySelector('button[cdkFocusInitial]')!.textContent!.trim()).toBe('Annulla');
  });

  it('è accessibile (WCAG 2.2 AA, senza il contrasto)', async () => {
    const el = await render(data);

    await expectNoA11yViolations(el);
  });
});
