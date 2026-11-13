import { TestBed } from '@angular/core/testing';
import { HttpErrorResponse } from '@angular/common/http';
import { MatDialog } from '@angular/material/dialog';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { expectNoA11yViolations } from '../../../../testing/a11y';
import { UserService } from '../../../core/services/http/user.service';
import { AdminSubscriptionsComponent } from './admin-subscriptions.component';
import { UserSubscriptionsDialogComponent } from './user-subscriptions-dialog.component';

const mario = { id: 4, name: 'Mario Rossi', email: 'mario@example.com', role: 'user' };
const anna = { id: 5, name: 'Anna Bianchi', email: 'anna@example.com', role: 'user' };

function page(data: unknown[], total = data.length) {
  return { data, total, current_page: 1, per_page: 10, last_page: 1 };
}

describe('AdminSubscriptionsComponent', () => {
  let list: ReturnType<typeof vi.fn>;
  let dialog: { open: ReturnType<typeof vi.fn> };

  async function render(response: unknown = page([mario, anna])) {
    list = vi.fn().mockReturnValue(response instanceof HttpErrorResponse ? throwError(() => response) : of(response));
    dialog = { open: vi.fn() };

    await TestBed.configureTestingModule({
      imports: [AdminSubscriptionsComponent],
      providers: [{ provide: UserService, useValue: { list } }, { provide: MatDialog, useValue: dialog }],
    }).compileComponents();

    const fixture = TestBed.createComponent(AdminSubscriptionsComponent);
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
    return { el: fixture.nativeElement as HTMLElement, fixture };
  }

  const text = (el: HTMLElement) => el.textContent!.replace(/\s+/g, ' ');

  it('mostra gli utenti in una tabella, con nome ed email', async () => {
    const { el } = await render();

    expect(list).toHaveBeenCalledWith({ page: 1, perPage: 10, search: '' });
    expect(el.querySelectorAll('table tr.mat-mdc-row').length).toBe(2);
    expect(text(el)).toContain('Mario Rossi');
    expect(text(el)).toContain('anna@example.com');
  });

  it('ha il paginatore, con il totale degli utenti', async () => {
    const { el } = await render(page([mario], 25));

    expect(el.querySelector('mat-paginator')).not.toBeNull();
    expect(text(el)).toContain('of 25');
  });

  it('cambiando pagina ricarica gli utenti di quella pagina', async () => {
    const { fixture } = await render(page([mario], 25));

    fixture.componentInstance['onPageChange']({ pageIndex: 1, pageSize: 10, length: 25 });

    expect(list).toHaveBeenLastCalledWith({ page: 2, perPage: 10, search: '' });
  });

  it('la ricerca riparte dalla prima pagina e passa il testo', async () => {
    vi.useFakeTimers();
    try {
      const { fixture } = await render(page([mario], 25));
      fixture.componentInstance['page'].set(3);

      fixture.componentInstance['onSearch']('mar');
      vi.advanceTimersByTime(400);

      expect(list).toHaveBeenLastCalledWith({ page: 1, perPage: 10, search: 'mar' });
    } finally {
      vi.useRealTimers();
    }
  });

  it('il pulsante di una riga apre la finestra con gli abbonamenti di quella persona', async () => {
    const { el } = await render();

    el.querySelector<HTMLButtonElement>('button[aria-label="Gestisci gli abbonamenti di Mario Rossi"]')!.click();

    expect(dialog.open).toHaveBeenCalledWith(UserSubscriptionsDialogComponent, expect.objectContaining({ data: { user: mario } }));
  });

  it('senza utenti lo dice, e con una ricerca senza risultati lo dice in modo diverso', async () => {
    const { el, fixture } = await render(page([]));
    expect(text(el)).toContain('Non ci sono utenti.');

    fixture.componentInstance['search'].set('zzz');
    fixture.detectChanges();
    expect(text(el)).toContain('Nessun utente trovato.');
  });

  it('se il caricamento fallisce lo scrive nella pagina', async () => {
    const { el } = await render(new HttpErrorResponse({ status: 500 }));

    expect(el.querySelector('[role="alert"]')).not.toBeNull();
  });

  it('è accessibile (WCAG 2.2 AA, senza il contrasto)', async () => {
    const { el } = await render();

    await expectNoA11yViolations(el);
  });
});
