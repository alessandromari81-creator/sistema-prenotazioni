import { Component, computed, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { apiErrorMessage } from '../../core/services/features/api-error';
import { NotificationService } from '../../core/services/features/notification.service';
import { AuthService } from '../../core/services/http/auth.service';
import { UserService } from '../../core/services/http/user.service';

/**
 * Il profilo dell'utente (/profile).
 *
 * I dati sono divisi in due, ognuno nel posto a cui appartiene:
 *  - nome, email e password sono di Keycloak: qui si mostrano in sola lettura, con due pulsanti che aprono il form mirato
 *    di Keycloak per cambiare la password o il nome (se si cambiassero nell'app, Laravel li riallineerebbe al token
 *    alla richiesta successiva); l'email non si cambia;
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
  private notify = inject(NotificationService); // avvisi a comparsa (successi 5 s, errori da chiudere)

  protected saving = signal(false);

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

  constructor() {
    this.announceActionResult();
  }

  /**
   * Se l'utente torna da un'azione chiesta a Keycloak (cambio password o nome), ne mostra l'esito in un avviso a comparsa
   * e poi lo dimentica, così non riappare se si torna su questa pagina più tardi.
   */
  private announceActionResult(): void {
    const result = this.auth.actionResult();
    if (!result) return;

    if (result.status === 'success') {
      this.notify.success(result.action === 'UPDATE_PASSWORD' ? 'Password aggiornata.' : 'Nome aggiornato.');
    } else if (result.status === 'cancelled') {
      this.notify.success('Operazione annullata: non è stato cambiato nulla.');
    } else {
      this.notify.error('L\'operazione su Keycloak non è riuscita. Riprova.');
    }

    this.auth.actionResult.set(null);
  }

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

    const address = this.form.getRawValue().address.trim();

    this.users.updateMe(address || null).subscribe({
      next: (user) => {
        this.auth.user.set(user);
        this.form.patchValue({ address: user.address ?? '' });
        this.notify.success('Profilo aggiornato.');
        this.saving.set(false);
      },
      error: (e: HttpErrorResponse) => {
        this.notify.error(apiErrorMessage(e, 'Salvataggio non riuscito.'));
        this.saving.set(false);
      },
    });
  }

  /** Porta a Keycloak per cambiare la password. */
  protected changePassword(): void {
    void this.auth.changePassword();
  }

  /** Porta a Keycloak per modificare nome e cognome. */
  protected editName(): void {
    void this.auth.editName();
  }
}
