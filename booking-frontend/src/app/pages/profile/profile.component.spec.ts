import { ComponentFixture, TestBed } from '@angular/core/testing';
import { HttpErrorResponse } from '@angular/common/http';
import { signal } from '@angular/core';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { expectNoA11yViolations } from '../../../testing/a11y';
import { User } from '../../core/models/models';
import { AuthService } from '../../core/services/http/auth.service';
import { UserService } from '../../core/services/http/user.service';
import { ProfileComponent } from './profile.component';

const mario: User = { id: 4, name: 'Mario Rossi', email: 'mario@example.com', role: 'user', address: 'Via Roma 1', teaches_courses: false };

describe('ProfileComponent', () => {
  let fixture: ComponentFixture<ProfileComponent>;
  let el: HTMLElement;
  let users: { updateMe: ReturnType<typeof vi.fn> };
  let auth: { user: ReturnType<typeof signal<User | null>>; manageAccount: ReturnType<typeof vi.fn> };

  async function render(user: User = mario) {
    users = { updateMe: vi.fn() };
    auth = { user: signal<User | null>(user), manageAccount: vi.fn().mockResolvedValue(undefined) };

    await TestBed.configureTestingModule({
      imports: [ProfileComponent],
      providers: [{ provide: UserService, useValue: users }, { provide: AuthService, useValue: auth }],
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
    expect(el.querySelector('.alert[role="status"]')!.textContent).toContain('Profilo aggiornato.');
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

    expect(el.querySelector('.alert--error[role="alert"]')!.textContent).toContain('L\'indirizzo non è valido.');
  });

  it('il pulsante apre la gestione dell\'account su Keycloak', async () => {
    await render();

    Array.from(el.querySelectorAll('button')).find((b) => b.textContent!.includes('Gestisci account'))!.click();

    expect(auth.manageAccount).toHaveBeenCalled();
  });

  it('è accessibile (WCAG 2.2 AA, senza il contrasto)', async () => {
    await render();

    await expectNoA11yViolations(el);
  });
});
