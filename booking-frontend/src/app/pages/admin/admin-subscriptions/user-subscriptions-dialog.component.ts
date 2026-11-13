import { DatePipe } from '@angular/common';
import { Component, inject, signal, OnInit } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { filter, switchMap } from 'rxjs';
import { Subscription, SubscriptionPlan, User } from '../../../core/models/models';
import { apiErrorMessage } from '../../../core/services/features/api-error';
import { ConfirmService } from '../../../core/services/features/confirm.service';
import { NotificationService } from '../../../core/services/features/notification.service';
import { SubscriptionService } from '../../../core/services/http/subscription.service';

/** La data di oggi in formato AAAA-MM-GG. */
const todayIso = (): string => new Date().toISOString().slice(0, 10);

/**
 * Validatore: la data di inizio, se indicata, non può essere precedente a oggi (il campo `min` del browser non
 * impedisce di scrivere a mano una data passata). Vuota è valida: l'abbonamento parte da oggi. Il server controlla comunque.
 */
function notInThePast(control: AbstractControl<string>): ValidationErrors | null {
  return control.value && control.value < todayIso() ? { pastDate: true } : null;
}

/** I dati che la pagina passa alla finestra: la persona di cui si gestiscono gli abbonamenti. */
export interface UserSubscriptionsDialogData {
  user: User;
}

/**
 * La finestra (modal) con gli abbonamenti di una persona: l'elenco, l'annullamento e l'assegnazione di un piano.
 * Le regole (piano attivo, nessuna sovrapposizione) le applica il server e il suo messaggio si mostra in un avviso.
 * Si apre dalla pagina "Utenti e abbonamenti" con MatDialog, che porta con sé focus, tasto Esc e ruolo "dialog".
 */
@Component({
  selector: 'app-user-subscriptions-dialog',
  standalone: true,
  imports: [DatePipe, ReactiveFormsModule, MatButtonModule, MatDialogModule, MatFormFieldModule, MatInputModule, MatSelectModule],
  templateUrl: './user-subscriptions-dialog.component.html',
  styleUrl: './user-subscriptions-dialog.component.scss',
})
export class UserSubscriptionsDialogComponent implements OnInit {
  protected data = inject<UserSubscriptionsDialogData>(MAT_DIALOG_DATA);
  private subscriptionApi = inject(SubscriptionService);
  private notify = inject(NotificationService); // avvisi a comparsa
  private confirmDialog = inject(ConfirmService);
  private fb = inject(FormBuilder);

  protected subscriptions = signal<Subscription[]>([]);
  protected loaded = signal(false); // evita di scrivere "nessun abbonamento" prima che l'elenco sia arrivato
  protected plans = signal<SubscriptionPlan[]>([]);

  /** Il modulo di assegnazione: il piano è obbligatorio; l'inizio (AAAA-MM-GG) è facoltativo, vuoto = parte da oggi. */
  protected form = this.fb.group({
    plan: this.fb.control<number | null>(null, Validators.required),
    startsOn: this.fb.nonNullable.control('', notInThePast),
  });

  /** La data di oggi (AAAA-MM-GG): il giorno di inizio non può essere precedente. */
  protected readonly today = todayIso();

  /** All'apertura carica i piani attivi (per il menu di assegnazione) e gli abbonamenti della persona. */
  ngOnInit(): void {
    this.subscriptionApi.plans().subscribe({
      next: (plans) => this.plans.set(plans),
      error: (e: HttpErrorResponse) => this.notify.error(apiErrorMessage(e, 'Impossibile caricare i piani.')),
    });

    this.loadSubscriptions();
  }

  /**
   * Assegna alla persona il piano scelto (dal giorno indicato, o da oggi). Se il modulo non è valido non chiama il
   * server e mostra gli errori sotto i campi.
   */
  protected assign(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const { plan, startsOn } = this.form.getRawValue();

    this.subscriptionApi.assign(this.data.user.id, plan!, startsOn || undefined).subscribe({
      next: () => {
        this.notify.success(`Abbonamento assegnato a ${this.data.user.name}.`);
        this.form.reset({ plan: null, startsOn: '' });
        this.loadSubscriptions();
      },
      error: (e: HttpErrorResponse) => this.notify.error(apiErrorMessage(e, 'Assegnazione non riuscita.')),
    });
  }

  /**
   * Annulla un abbonamento dopo aver chiesto conferma con una finestra. Se l'utente rinuncia non succede nulla.
   * Le iscrizioni ai corsi già fatte restano valide.
   */
  protected cancel(subscription: Subscription): void {
    this.confirmDialog
      .confirm({
        title: 'Annullare l\'abbonamento?',
        message: `L'abbonamento ${subscription.plan?.name ?? ''} di ${this.data.user.name} verrà annullato. Le iscrizioni ai corsi già fatte restano valide.`,
        confirmLabel: 'Annulla abbonamento',
        cancelLabel: 'Mantieni',
      })
      .pipe(
        filter((confirmed) => confirmed),
        switchMap(() => this.subscriptionApi.cancel(subscription.id))
      )
      .subscribe({
        next: () => {
          this.notify.success('Abbonamento annullato.');
          this.loadSubscriptions();
        },
        error: (e: HttpErrorResponse) => this.notify.error(apiErrorMessage(e, 'Annullamento non riuscito.')),
      });
  }

  /** Lo stato dell'abbonamento a parole: "Attivo", "Annullato", "Scaduto" o "Non ancora iniziato". */
  protected statusText(s: Subscription): string {
    if (s.cancelled_at) return 'Annullato';
    if (s.is_active) return 'Attivo';
    return s.starts_on > this.today ? 'Non ancora iniziato' : 'Scaduto';
  }

  /** Carica gli abbonamenti della persona. */
  private loadSubscriptions(): void {
    this.subscriptionApi.ofUser(this.data.user.id).subscribe({
      next: (subscriptions) => {
        this.subscriptions.set(subscriptions);
        this.loaded.set(true);
      },
      error: (e: HttpErrorResponse) => this.notify.error(apiErrorMessage(e, 'Impossibile caricare gli abbonamenti.')),
    });
  }
}
