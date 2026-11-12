import { Component, inject, signal, OnInit } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { SubscriptionPlanInput } from '../../../core/models/models';
import { apiErrorMessage } from '../../../core/services/features/api-error';
import { SubscriptionService } from '../../../core/services/http/subscription.service';

/**
 * Form per creare (/admin/plans/new) e modificare (/admin/plans/:id/edit) un piano di abbonamento.
 * Cambiare un piano non modifica gli abbonamenti già assegnati: hanno già le loro date.
 */
@Component({
  selector: 'app-admin-plan-form',
  standalone: true,
  imports: [ReactiveFormsModule, RouterLink, MatButtonModule, MatFormFieldModule, MatInputModule, MatSlideToggleModule],
  templateUrl: './admin-plan-form.component.html',
})
export class AdminPlanFormComponent implements OnInit {
  private fb = inject(FormBuilder);
  private api = inject(SubscriptionService);
  private route = inject(ActivatedRoute);
  private router = inject(Router);

  /** null = si sta creando un piano; un numero = si sta modificando quel piano. */
  protected planId: number | null = null;

  protected loading = signal(false);
  protected saving = signal(false);
  protected error = signal<string | null>(null);

  /** Il form: le regole rispecchiano quelle del backend. */
  protected form = this.fb.nonNullable.group({
    name: ['', [Validators.required, Validators.maxLength(255)]],
    description: ['', [Validators.maxLength(2000)]],
    duration_days: [30, [Validators.required, Validators.min(1), Validators.max(1095)]],
    max_courses: [2, [Validators.required, Validators.min(1), Validators.max(50)]],
    is_active: [true],
  });

  /** Se nell'indirizzo c'è un id siamo in modifica: carica il piano (dall'elenco) e riempie il form. */
  ngOnInit(): void {
    const idParam = this.route.snapshot.paramMap.get('id');
    if (!idParam) return;

    this.planId = Number(idParam);
    this.loading.set(true);

    this.api.plans(true).subscribe({
      next: (plans) => {
        const plan = plans.find((p) => p.id === this.planId);

        if (plan) {
          this.form.patchValue({ ...plan, description: plan.description ?? '' });
        } else {
          this.error.set('Piano non trovato.');
        }
        this.loading.set(false);
      },
      error: (e: HttpErrorResponse) => {
        this.error.set(apiErrorMessage(e, 'Impossibile caricare il piano.'));
        this.loading.set(false);
      },
    });
  }

  /** Invia il form: crea o modifica a seconda del caso e torna all'elenco. */
  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.saving.set(true);
    this.error.set(null);

    const raw = this.form.getRawValue();
    const payload: SubscriptionPlanInput = { ...raw, description: raw.description || null };

    const request$ = this.planId === null ? this.api.createPlan(payload) : this.api.updatePlan(this.planId, payload);

    request$.subscribe({
      next: () => this.router.navigate(['/admin/plans']),
      error: (e: HttpErrorResponse) => {
        this.error.set(apiErrorMessage(e, 'Salvataggio non riuscito.'));
        this.saving.set(false);
      },
    });
  }
}
