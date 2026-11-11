import { Component, OnInit, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { AuthService } from '../../../core/services/http/auth.service';
import { apiErrorMessage } from '../../../core/services/features/api-error';
import { MatCardModule } from '@angular/material/card';
import { MatButtonModule } from '@angular/material/button';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';

/**
 * Pagina aperta dal link nella mail: /confirm-registration?email=...&token=...
 * Appena si carica invia email e token al server, che crea l'utente.
 */
@Component({
  selector: 'app-confirm-registration',
  standalone: true,
  imports: [MatCardModule, MatButtonModule, MatProgressSpinnerModule, RouterLink],
  templateUrl: './confirm-registration.component.html',
  styleUrl: './confirm-registration.component.scss',
})
export class ConfirmRegistrationComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private auth = inject(AuthService);

  status = signal<'loading' | 'success' | 'error'>('loading');
  message = signal('');

  ngOnInit(): void {
    const email = this.route.snapshot.queryParamMap.get('email');
    const token = this.route.snapshot.queryParamMap.get('token');

    // Link incompleto: inutile chiamare il server
    if (!email || !token) {
      this.message.set('Link non valido.');
      this.status.set('error');
      return;
    }

    this.auth.confirmRegistration(email, token).subscribe({
      next: (res) => {
        this.message.set(res.message);
        this.status.set('success');
      },
      error: (e: HttpErrorResponse) => {
        this.message.set(apiErrorMessage(e, 'Conferma non riuscita'));
        this.status.set('error');
      },
    });
  }
}
