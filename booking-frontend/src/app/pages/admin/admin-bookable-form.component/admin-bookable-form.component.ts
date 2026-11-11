import { Component, inject, signal, OnInit } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { BookingService } from '../../../core/services/http/booking.service';
import { BookableInput } from '../../../core/models/models';
import { apiErrorMessage } from '../../../core/services/features/api-error';

// Form unico per creare (/admin/bookables/new) e modificare (/admin/bookables/:id/edit) una risorsa
@Component({
  selector: 'app-admin-bookable-form',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    RouterLink,
    MatCardModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatSlideToggleModule,
    MatSnackBarModule,
  ],
  templateUrl: './admin-bookable-form.component.html',
  styleUrl: './admin-bookable-form.component.scss',
})
export class AdminBookableFormComponent implements OnInit {
  private fb = inject(FormBuilder);
  private api = inject(BookingService);
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private snack = inject(MatSnackBar); // notifiche a comparsa

  // null = stiamo creando una risorsa nuova; un numero = stiamo modificando quella risorsa
  bookableId: number | null = null;

  loading = signal(false);
  saving = signal(false);

  // Form reattivo: le validazioni rispecchiano quelle del backend (nome, minuti 5-480)
  form = this.fb.nonNullable.group({
    name: ['', [Validators.required, Validators.maxLength(255)]],
    description: [''],
    slot_minutes: [30, [Validators.required, Validators.min(5), Validators.max(480)]],
    is_active: [true],
  });

  /** Se nell'indirizzo c'è un id siamo in modifica: carica la risorsa e riempie il form. */
  ngOnInit(): void {
    const idParam = this.route.snapshot.paramMap.get('id');
    if (!idParam) return; // nessun id = creazione, il form parte con i valori predefiniti

    this.bookableId = Number(idParam);
    this.loading.set(true);
    this.api.getBookable(this.bookableId).subscribe({
      next: (b) => {
        this.form.patchValue({
          name: b.name,
          description: b.description ?? '',
          slot_minutes: b.slot_minutes,
          is_active: b.is_active,
        });
        this.loading.set(false);
      },
      error: (e: HttpErrorResponse) => {
        this.snack.open(apiErrorMessage(e, 'Impossibile caricare la risorsa'), 'Chiudi', { duration: 5000 });
        this.loading.set(false);
      },
    });
  }

  /** Invia il form: crea o modifica a seconda del caso, poi torna all'elenco. */
  submit(): void {
    if (this.form.invalid) return;

    this.saving.set(true);

    const raw = this.form.getRawValue();
    const payload: BookableInput = {
      name: raw.name,
      description: raw.description || null, // testo vuoto -> null (il campo è facoltativo)
      slot_minutes: raw.slot_minutes,
      is_active: raw.is_active,
    };

    const request$ =
      this.bookableId === null
        ? this.api.createBookable(payload)
        : this.api.updateBookable(this.bookableId, payload);

    request$.subscribe({
      next: () => {
        this.snack.open('Risorsa salvata ✔', 'Chiudi', { duration: 3000 });
        this.router.navigate(['/admin/bookables']);
      },
      error: (e: HttpErrorResponse) => {
        this.snack.open(apiErrorMessage(e, 'Salvataggio non riuscito'), 'Chiudi', { duration: 6000 });
        this.saving.set(false);
      },
    });
  }
}