import { Component, computed, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { apiErrorMessage } from '../../core/services/features/api-error';
import { AuthService } from '../../core/services/http/auth.service';
import { UserService } from '../../core/services/http/user.service';

/**
 * Il profilo dell'utente (/profile).
 *
 * I dati sono divisi in due, ognuno nel posto a cui appartiene:
 *  - nome, email e password sono di Keycloak: qui si mostrano in sola lettura, con un pulsante che apre la gestione
 *    dell'account su Keycloak (se si cambiassero nell'app, Laravel li riallineerebbe al token alla richiesta successiva);
 *  - l'indirizzo è un dato dell'applicazione: si modifica qui e lo conserva Laravel.
 */
@Component({
  selector: 'app-profile',
  standalone: true,
  imports: [ReactiveFormsModule, MatButtonModule, MatFormFieldModule, MatInputModule],
  templateUrl: './profile.component.html',
})
export class ProfileComponent {
  protected auth = inject(AuthService);
  private users = inject(UserService);
  private fb = inject(FormBuilder);

  protected saving = signal(false);
  protected notice = signal<string | null>(null);
  protected error = signal<string | null>(null);

  /** Il ruolo scritto a parole, per esempio "Amministratore" o "Utente, istruttore". */
  protected roleText = computed(() => {
    const user = this.auth.user();
    if (!user) return '';

    const parts = [user.role === 'admin' ? 'Amministratore' : 'Utente'];
    if (user.teaches_courses) parts.push('istruttore');
    return parts.join(', ');
  });

  /** Il form: solo l'indirizzo, che parte con il valore attuale (stesso limite di lunghezza del server). */
  protected form = this.fb.nonNullable.group({
    address: [this.auth.user()?.address ?? '', [Validators.maxLength(255)]],
  });

  /**
   * Salva l'indirizzo. Un campo vuoto lo cancella (si invia null). Se il salvataggio riesce il profilo mostrato in
   * tutta l'app si aggiorna con quello restituito dal server.
   */
  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.saving.set(true);
    this.notice.set(null);
    this.error.set(null);

    const address = this.form.getRawValue().address.trim();

    this.users.updateMe(address || null).subscribe({
      next: (user) => {
        this.auth.user.set(user);
        this.form.patchValue({ address: user.address ?? '' });
        this.notice.set('Profilo aggiornato.');
        this.saving.set(false);
      },
      error: (e: HttpErrorResponse) => {
        this.error.set(apiErrorMessage(e, 'Salvataggio non riuscito.'));
        this.saving.set(false);
      },
    });
  }

  /** Apre su Keycloak la gestione dell'account (nome, email, password). */
  protected manageAccount(): void {
    void this.auth.manageAccount();
  }
}
