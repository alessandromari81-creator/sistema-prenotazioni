import { Component, computed, inject, signal, OnInit } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { filter, switchMap } from 'rxjs';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatChipsModule } from '@angular/material/chips';
import { MatIconModule } from '@angular/material/icon';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { Availability, Bookable } from '../../../core/models/models';
import { BookingService } from '../../../core/services/http/booking.service';
import { apiErrorMessage } from '../../../core/services/features/api-error';
import { ConfirmService } from '../../../core/services/features/confirm.service';


// Giorni della settimana: il numero è lo stesso usato dal backend (1 = lunedì ... 7 = domenica)
const DAYS = [
  { n: 1, label: 'Lunedì' },
  { n: 2, label: 'Martedì' },
  { n: 3, label: 'Mercoledì' },
  { n: 4, label: 'Giovedì' },
  { n: 5, label: 'Venerdì' },
  { n: 6, label: 'Sabato' },
  { n: 7, label: 'Domenica' },
];

// Pagina admin: gestione delle fasce orarie settimanali di una sala
@Component({
  selector: 'app-admin-availabilities',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    RouterLink,
    MatCardModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatButtonModule,
    MatChipsModule,
    MatIconModule,
    MatSnackBarModule,
  ],
  templateUrl: './admin-availabilities.component.html',
  styleUrl: './admin-availabilities.component.scss',
})
export class AdminAvailabilitiesComponent implements OnInit {
  private fb = inject(FormBuilder);
  private api = inject(BookingService);
  private route = inject(ActivatedRoute);
  private snack = inject(MatSnackBar); // notifiche a comparsa
  private confirmDialog = inject(ConfirmService);

  days = DAYS;

  bookable = signal<Bookable | null>(null);
  availabilities = signal<Availability[]>([]);
  loading = signal(true);
  saving = signal(false);
  error = signal<string | null>(null); // solo per l'errore di caricamento della pagina

  // Per ogni giorno, le fasce ordinate per orario di inizio.
  // computed() si ricalcola da solo quando cambia "availabilities"
  grouped = computed(() =>
    DAYS.map((d) => ({
      ...d,
      items: this.availabilities()
        .filter((a) => a.day_of_week === d.n)
        .sort((x, y) => x.start_time.localeCompare(y.start_time)),
    }))
  );

  // Form reattivo: un input di tipo "time" restituisce il testo "HH:MM", il formato che il backend si aspetta
  form = this.fb.nonNullable.group({
    day_of_week: [1, Validators.required],
    start_time: ['09:00', Validators.required],
    end_time: ['13:00', Validators.required],
  });

  /** All'apertura legge l'id dall'indirizzo e carica la sala con le sue fasce. */
  ngOnInit(): void {
    const id = Number(this.route.snapshot.paramMap.get('id'));

    this.api.getBookable(id).subscribe({
      next: (b) => {
        this.bookable.set(b);
        this.availabilities.set(b.availabilities ?? []);
        this.loading.set(false);
      },
      error: (e: HttpErrorResponse) => {
        this.error.set(apiErrorMessage(e, 'Impossibile caricare la sala'));
        this.loading.set(false);
      },
    });
  }

  /** Aggiunge la fascia; se il backend la rifiuta (es. sovrapposizione) mostra il suo messaggio. */
  add(): void {
    const b = this.bookable();
    if (!b || this.form.invalid) return;

    const value = this.form.getRawValue();

    // Controllo veloce lato browser (i testi "HH:MM" si confrontano correttamente);
    // la regola vera resta comunque nel backend
    if (value.end_time <= value.start_time) {
      this.snack.open("L'ora di fine deve essere dopo quella di inizio", 'Chiudi', { duration: 5000 });
      return;
    }
    console.log('Slot selezionato:', value);
    this.saving.set(true);

    this.api.addAvailability(b.id, value).subscribe({
      next: (created) => {
        // Aggiunge la nuova fascia all'elenco senza ricaricare la pagina
        this.availabilities.update((list) => [...list, created]);
        this.saving.set(false);
        this.snack.open('Fascia aggiunta ✔', 'Chiudi', { duration: 3000 });
      },
      error: (e: HttpErrorResponse) => {
        this.snack.open(apiErrorMessage(e, 'Impossibile aggiungere la fascia'), 'Chiudi', { duration: 6000 });
        this.saving.set(false);
      },
    });
  }

  /** Chiede conferma ed elimina la fascia. */
  remove(a: Availability): void {
    this.confirmDialog
      .confirm({
        title: 'Eliminare la fascia oraria?',
        message:
          `La fascia ${a.start_time.slice(0, 5)}–${a.end_time.slice(0, 5)} verrà eliminata. ` +
          'Le prenotazioni già fatte restano, ma non si potranno prenotare nuovi slot in quella fascia.',
        confirmLabel: 'Elimina fascia',
      })
      .pipe(
        filter((confirmed) => confirmed),
        switchMap(() => this.api.deleteAvailability(a.id))
      )
      .subscribe({
      next: () => {
        this.availabilities.update((list) => list.filter((x) => x.id !== a.id));
        this.snack.open('Fascia eliminata', 'Chiudi', { duration: 3000 });
      },
      error: (e: HttpErrorResponse) =>
        this.snack.open(apiErrorMessage(e, 'Eliminazione non riuscita'), 'Chiudi', { duration: 6000 }),
    });
  }
}