import { ComponentFixture, TestBed } from '@angular/core/testing';
import { HttpErrorResponse } from '@angular/common/http';
import { signal } from '@angular/core';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { expectNoA11yViolations } from '../../../testing/a11y';
import { User } from '../../core/models/models';
import { MatSnackBar } from '@angular/material/snack-bar';
import { AuthService } from '../../core/services/http/auth.service';
import { UserService } from '../../core/services/http/user.service';
import { ProfileComponent } from './profile.component';

const mario: User = { id: 4, name: 'Mario Rossi', email: 'mario@example.com', role: 'user', address: 'Via Roma 1', teaches_courses: false };

describe('ProfileComponent', () => {
  let fixture: ComponentFixture<ProfileComponent>;
  let el: HTMLElement;
  let users: { updateMe: ReturnType<typeof vi.fn> };
  let snack: { open: ReturnType<typeof vi.fn> };
  let auth: {
    user: ReturnType<typeof signal<User | null>>;
    actionResult: ReturnType<typeof signal<{ status: string; action?: string } | null>>;
    changePassword: ReturnType<typeof vi.fn>;
    editName: ReturnType<typeof vi.fn>;
  };

  async function render(user: User = mario, actionResult: { status: string; action?: string } | null = null) {
    users = { updateMe: vi.fn() };
    snack = { open: vi.fn() };
    auth = {
      user: signal<User | null>(user),
      actionResult: signal(actionResult),
      changePassword: vi.fn().mockResolvedValue(undefined),
      editName: vi.fn().mockResolvedValue(undefined),
    };

    await TestBed.configureTestingModule({
      imports: [ProfileComponent],
      providers: [{ provide: UserService, useValue: users }, { provide: AuthService, useValue: auth }, { provide: MatSnackBar, useValue: snack }],
    }).compileComponents();

    fixture = TestBed.createComponent(ProfileComponent);
    fixture.detectChanges();
    el = fixture.nativeElement as HTMLElement;
  }

  const text = () => el.textContent!.replace(/\s+/g, ' ');
  const addressInput = () => el.querySelector<HTMLInputElement>('[formControlName="address"]')!;
  const type = (value: string) => {
    addressInput().value = value;
    addressInput().dispatchEvent(new Event('input'));
  };
  const submit = () => el.querySelector<HTMLButtonElement>('button[type="submit"]')!.click();

  it('mostra nome, email e ruolo in sola lettura', async () => {
    await render();

    expect(el.querySelector('h1')!.textContent).toBe('Il mio profilo');
    expect(text()).toContain('Mario Rossi');
    expect(text()).toContain('mario@example.com');
    expect(text()).toContain('Utente');
    // Nome ed email non sono campi modificabili: sono di Keycloak
    expect(el.querySelectorAll('input')).toHaveLength(1);
  });

  it('scrive il ruolo a parole, anche per amministratori e istruttori', async () => {
    await render({ ...mario, role: 'admin', teaches_courses: true });

    expect(text()).toContain('Amministratore, istruttore');
  });

  it('parte con l\'indirizzo attuale', async () => {
    await render();

    expect(addressInput().value).toBe('Via Roma 1');
    expect(addressInput().getAttribute('autocomplete')).toBe('street-address');
  });

  it('salvando aggiorna il profilo di tutta l\'app e annuncia l\'esito', async () => {
    await render();
    users.updateMe.mockReturnValue(of({ ...mario, address: 'Via Verdi 2' }));

    type('  Via Verdi 2  ');
    submit();
    fixture.detectChanges();

    expect(users.updateMe).toHaveBeenCalledWith('Via Verdi 2'); // spazi tolti
    expect(auth.user()?.address).toBe('Via Verdi 2');
    // L'esito è un avviso a comparsa che sparisce da solo dopo 5 secondi
    expect(snack.open).toHaveBeenCalledWith('Profilo aggiornato.', 'Chiudi', { duration: 5000 });
  });

  it('un campo vuoto cancella l\'indirizzo (invia null)', async () => {
    await render();
    users.updateMe.mockReturnValue(of({ ...mario, address: null }));

    type('');
    submit();

    expect(users.updateMe).toHaveBeenCalledWith(null);
  });

  it('non invia un indirizzo troppo lungo e spiega il motivo', async () => {
    await render();

    type('a'.repeat(256));
    submit();
    fixture.detectChanges();

    expect(users.updateMe).not.toHaveBeenCalled();
    expect(text()).toContain('al massimo 255 caratteri');
  });

  it('se il server rifiuta mostra il motivo in un avviso', async () => {
    await render();
    users.updateMe.mockReturnValue(throwError(() => new HttpErrorResponse({ status: 422, error: { errors: { address: ['L\'indirizzo non è valido.'] } } })));

    submit();
    fixture.detectChanges();

    // Un errore resta finché non lo si chiude ed è annunciato con priorità alta
    expect(snack.open).toHaveBeenCalledWith('L\'indirizzo non è valido.', 'Chiudi', { duration: 0, politeness: 'assertive' });
  });

  const button = (label: string) => Array.from(el.querySelectorAll('button')).find((b) => b.textContent!.includes(label))!;

  it('non rimanda più alla console dell\'account (niente voce "Applications")', async () => {
    await render();

    expect(text()).not.toContain('Gestisci account');
    expect(text()).toContain('L\'email non si può modificare');
  });

  it('"Cambia password" porta al form mirato di Keycloak', async () => {
    await render();

    button('Cambia password').click();

    expect(auth.changePassword).toHaveBeenCalled();
    expect(auth.editName).not.toHaveBeenCalled();
  });

  it('"Modifica nome e cognome" porta al form mirato di Keycloak', async () => {
    await render();

    button('Modifica nome e cognome').click();

    expect(auth.editName).toHaveBeenCalled();
    expect(auth.changePassword).not.toHaveBeenCalled();
  });

  it('senza azioni precedenti non mostra nessun esito', async () => {
    await render();

    expect(snack.open).not.toHaveBeenCalled();
    expect(el.querySelectorAll('.alert')).toHaveLength(0);
  });

  it('al ritorno da un cambio di password mostra l\'esito in un avviso a comparsa', async () => {
    await render(mario, { status: 'success', action: 'UPDATE_PASSWORD' });

    expect(snack.open).toHaveBeenCalledWith('Password aggiornata.', 'Chiudi', { duration: 5000 });
  });

  it('al ritorno da una modifica del nome annuncia l\'esito', async () => {
    await render(mario, { status: 'success', action: 'UPDATE_PROFILE' });

    expect(snack.open).toHaveBeenCalledWith('Nome aggiornato.', 'Chiudi', { duration: 5000 });
  });

  it('se l\'azione è stata annullata lo dice, senza toni d\'errore', async () => {
    await render(mario, { status: 'cancelled', action: 'UPDATE_PASSWORD' });

    expect(snack.open).toHaveBeenCalledWith('Operazione annullata: non è stato cambiato nulla.', 'Chiudi', { duration: 5000 });
  });

  it('se l\'azione è fallita mostra un errore che resta finché non lo si chiude', async () => {
    await render(mario, { status: 'error', action: 'UPDATE_PASSWORD' });

    expect(snack.open).toHaveBeenCalledWith('L\'operazione su Keycloak non è riuscita. Riprova.', 'Chiudi', { duration: 0, politeness: 'assertive' });
  });

  it('l\'esito si mostra una volta sola: tornando sulla pagina più tardi non riappare', async () => {
    await render(mario, { status: 'success', action: 'UPDATE_PASSWORD' });
    expect(auth.actionResult()).toBeNull(); // consumato

    snack.open.mockClear();
    TestBed.createComponent(ProfileComponent); // si riapre la pagina

    expect(snack.open).not.toHaveBeenCalled();
  });

  it('non lascia avvisi fissi nella pagina (stanno tutti nella snackbar)', async () => {
    await render(mario, { status: 'success', action: 'UPDATE_PASSWORD' });

    expect(el.querySelectorAll('.alert, [role="alert"], [role="status"]')).toHaveLength(0);
  });

  it('è accessibile (WCAG 2.2 AA, senza il contrasto)', async () => {
    await render();

    await expectNoA11yViolations(el);
  });
});
