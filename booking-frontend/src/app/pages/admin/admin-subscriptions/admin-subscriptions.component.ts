import { DatePipe } from '@angular/common';
import { Component, inject, signal, OnInit } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { Subscription, SubscriptionPlan, User } from '../../../core/models/models';
import { apiErrorMessage } from '../../../core/services/features/api-error';
import { SubscriptionService } from '../../../core/services/http/subscription.service';
import { UserService } from '../../../core/services/http/user.service';

/**
 * Gli abbonamenti degli utenti (/admin/subscriptions): l'amministratore cerca una persona, vede i suoi abbonamenti,
 * le assegna un piano oppure ne annulla uno. Le regole (piano attivo, nessuna sovrapposizione) le applica il server
 * e il suo messaggio si mostra a video.
 */
@Component({
  selector: 'app-admin-subscriptions',
  standalone: true,
  imports: [DatePipe, FormsModule, MatButtonModule, MatFormFieldModule, MatInputModule, MatSelectModule],
  templateUrl: './admin-subscriptions.component.html',
})
export class AdminSubscriptionsComponent implements OnInit {
  private userApi = inject(UserService);
  private subscriptionApi = inject(SubscriptionService);

  protected query = signal('');
  protected users = signal<User[]>([]);
  protected searched = signal(false);
  protected selected = signal<User | null>(null);
  protected subscriptions = signal<Subscription[]>([]);
  protected plans = signal<SubscriptionPlan[]>([]);
  protected planId = signal<number | null>(null);
  protected startsOn = signal(''); // formato AAAA-MM-GG; vuoto = parte da oggi
  protected error = signal<string | null>(null);
  protected notice = signal<string | null>(null);

  /** La data di oggi (AAAA-MM-GG): il giorno di inizio non può essere precedente. */
  protected readonly today = new Date().toISOString().slice(0, 10);

  /** All'apertura carica i piani attivi (servono al menu di assegnazione) e mostra i primi utenti. */
  ngOnInit(): void {
    this.subscriptionApi.plans().subscribe({
      next: (plans) => this.plans.set(plans),
      error: (e: HttpErrorResponse) => this.error.set(apiErrorMessage(e, 'Impossibile caricare i piani.')),
    });

    this.searchUsers();
  }

  /** Cerca gli utenti per nome o email (senza testo mostra i primi in ordine alfabetico). */
  protected searchUsers(): void {
    this.error.set(null);

    this.userApi.list({ search: this.query().trim(), perPage: 10 }).subscribe({
      next: (response) => {
        this.users.set(response.data);
        this.searched.set(true);
      },
      error: (e: HttpErrorResponse) => this.error.set(apiErrorMessage(e, 'Ricerca non riuscita.')),
    });
  }

  /** Sceglie un utente e carica i suoi abbonamenti. */
  protected select(user: User): void {
    this.selected.set(user);
    this.notice.set(null);
    this.error.set(null);
    this.loadSubscriptions(user);
  }

  /** Assegna all'utente scelto il piano indicato (dal giorno scelto, o da oggi). */
  protected assign(): void {
    const user = this.selected();
    const planId = this.planId();
    if (!user || !planId) return;

    this.error.set(null);
    this.notice.set(null);

    this.subscriptionApi.assign(user.id, planId, this.startsOn() || undefined).subscribe({
      next: () => {
        this.notice.set(`Abbonamento assegnato a ${user.name}.`);
        this.loadSubscriptions(user);
      },
      error: (e: HttpErrorResponse) => this.error.set(apiErrorMessage(e, 'Assegnazione non riuscita.')),
    });
  }

  /** Annulla un abbonamento dopo conferma. Le iscrizioni già fatte restano valide. */
  protected cancel(subscription: Subscription): void {
    const user = this.selected();
    if (!user || !confirm('Annullare questo abbonamento? Le iscrizioni ai corsi già fatte restano valide.')) return;

    this.error.set(null);
    this.notice.set(null);

    this.subscriptionApi.cancel(subscription.id).subscribe({
      next: () => {
        this.notice.set('Abbonamento annullato.');
        this.loadSubscriptions(user);
      },
      error: (e: HttpErrorResponse) => this.error.set(apiErrorMessage(e, 'Annullamento non riuscito.')),
    });
  }

  /** Lo stato dell'abbonamento a parole: "Attivo", "Annullato", "Scaduto" o "Non ancora iniziato". */
  protected statusText(s: Subscription): string {
    if (s.cancelled_at) return 'Annullato';
    if (s.is_active) return 'Attivo';
    return s.starts_on > this.today ? 'Non ancora iniziato' : 'Scaduto';
  }

  /** Carica gli abbonamenti di un utente. */
  private loadSubscriptions(user: User): void {
    this.subscriptionApi.ofUser(user.id).subscribe({
      next: (subscriptions) => this.subscriptions.set(subscriptions),
      error: (e: HttpErrorResponse) => this.error.set(apiErrorMessage(e, 'Impossibile caricare gli abbonamenti.')),
    });
  }
}
