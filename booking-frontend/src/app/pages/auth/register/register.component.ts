import { Component, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../../core/services/http/auth.service';
import { apiErrorMessage } from '../../../core/services/features/api-error';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    MatCardModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    RouterLink,
  ],
  templateUrl: './register.component.html',
  styleUrl: './register.component.scss',
})
export class RegisterComponent {
  private fb = inject(FormBuilder);
  private auth = inject(AuthService);

  loading = signal(false);
  error = signal<string | null>(null);
  // Dopo l'invio mostriamo "controlla la tua email" al posto del form
  sent = signal(false);

  form = this.fb.nonNullable.group({
    name: ['', [Validators.required, Validators.maxLength(255)]],
    email: ['', [Validators.required, Validators.email, Validators.maxLength(255)]],
    password: ['', [Validators.required, Validators.minLength(8), Validators.maxLength(255)]],
    address: ['', [Validators.required, Validators.maxLength(255)]],
  });

  submit(): void {
    this.loading.set(true);
    this.error.set(null);

    this.auth.register(this.form.getRawValue()).subscribe({
      next: () => {
        this.sent.set(true);
        this.loading.set(false);
      },
      error: (e: HttpErrorResponse) => {
        // 429 = troppe richieste: il server indica quanti secondi attendere
        const msg =
          e.status === 429 && e.error?.retry_after
            ? `Troppe richieste. Riprova tra ${e.error.retry_after} secondi.`
            : apiErrorMessage(e, 'Registrazione non riuscita');
        this.error.set(msg);
        this.loading.set(false);
      },
    });
  }
}
