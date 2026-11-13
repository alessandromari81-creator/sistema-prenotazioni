import { Component, inject, signal, OnInit } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { RouterLink } from '@angular/router';
import { filter, switchMap } from 'rxjs';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatTableModule } from '@angular/material/table';
import { SubscriptionPlan } from '../../../core/models/models';
import { apiErrorMessage } from '../../../core/services/features/api-error';
import { ConfirmService } from '../../../core/services/features/confirm.service';
import { NotificationService } from '../../../core/services/features/notification.service';
import { SubscriptionService } from '../../../core/services/http/subscription.service';

/**
 * I piani di abbonamento (/admin/plans): elenco completo con le azioni di modifica ed eliminazione.
 * Un piano già assegnato a qualcuno non si elimina (il server lo rifiuta): va disattivato.
 */
@Component({
  selector: 'app-admin-plans',
  standalone: true,
  imports: [RouterLink, MatButtonModule, MatIconModule, MatTableModule],
  templateUrl: './admin-plans.component.html',
})
export class AdminPlansComponent implements OnInit {
  private api = inject(SubscriptionService);
  private notify = inject(NotificationService); // avvisi a comparsa
  private confirmDialog = inject(ConfirmService);

  protected plans = signal<SubscriptionPlan[]>([]);
  protected loading = signal(true);
  protected error = signal<string | null>(null);

  /** Le colonne della tabella. */
  protected readonly columns = ['name', 'duration', 'courses', 'status', 'actions'];

  /** All'apertura carica tutti i piani, anche quelli disattivati. */
  ngOnInit(): void {
    this.load();
  }

  /** Carica l'elenco dei piani. */
  private load(): void {
    this.loading.set(true);

    this.api.plans(true).subscribe({
      next: (plans) => {
        this.plans.set(plans);
        this.loading.set(false);
      },
      error: (e: HttpErrorResponse) => {
        this.error.set(apiErrorMessage(e, 'Impossibile caricare i piani.'));
        this.loading.set(false);
      },
    });
  }

  /** Elimina un piano dopo conferma; se è già stato assegnato il server rifiuta e il motivo si mostra a video. */
  protected remove(plan: SubscriptionPlan): void {
    this.confirmDialog
      .confirm({
        title: 'Eliminare il piano?',
        message: `Il piano "${plan.name}" verrà eliminato.`,
        confirmLabel: 'Elimina piano',
      })
      .pipe(
        filter((confirmed) => confirmed),
        switchMap(() => this.api.deletePlan(plan.id))
      )
      .subscribe({
      next: () => {
        this.notify.success(`Il piano ${plan.name} è stato eliminato.`);
        this.load();
      },
      error: (e: HttpErrorResponse) => this.notify.error(apiErrorMessage(e, 'Eliminazione non riuscita.')),
    });
  }
}
