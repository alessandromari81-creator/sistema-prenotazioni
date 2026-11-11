import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { BookingService } from '../../../core/services/http/booking.service';
import { apiErrorMessage } from '../../../core/services/features/api-error';
import { Bookable, Slot } from '../../../core/models/models';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatChipsModule } from '@angular/material/chips';
import { MatButtonModule } from '@angular/material/button';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { DateTime } from 'luxon';
import { LuxonService } from '../../../core/services/features/luxon-service';
 
@Component({
  selector: 'app-bookable-detail',
  standalone: true,
  imports: [
    DatePipe,
    RouterLink,
    MatCardModule,
    MatFormFieldModule,
    MatInputModule,
    MatDatepickerModule,
    MatChipsModule,
    MatButtonModule,
    MatProgressSpinnerModule,
    MatSnackBarModule,
  ],
  templateUrl: './bookable-detail.component.html',
  styleUrl: './bookable-detail.component.scss',
})
export class BookableDetailComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private api = inject(BookingService);
  private snack = inject(MatSnackBar); // servizio per le notifiche a comparsa
  private luxonService = inject(LuxonService);

  // Id letto dall'URL (/bookables/:id)
  readonly id = Number(this.route.snapshot.paramMap.get('id'));
  readonly minDate = DateTime.local().startOf('day');

  bookable = signal<Bookable | null>(null);
  date = signal(this.luxonService.todayLocal());
  slots = signal<Slot[]>([]);
  selected = signal<Slot | null>(null);
  loading = signal(false);
  saving = signal(false);
  error = signal<string | null>(null);

  /** La stessa data come oggetto Date: è ciò che si aspetta il datepicker. */
  dateObj = computed(() => this.luxonService.fromLocalString(this.date()));

  ngOnInit(): void {
    this.api.getBookable(this.id).subscribe({
      next: (b) => this.bookable.set(b),
      error: () => this.error.set('Risorsa non trovata'),
    });
    this.loadSlots();
  }

  /** Chiamato dal datepicker: converte il Date in stringa e passa al flusso di cambio data. */
  onPickerChange(value: DateTime | null): void {
    if (!value || !value.isValid) return;
    this.onDateChange(this.luxonService.toLocalString(value));
  }

  /** Cambio di data: azzera la scelta e ricarica gli slot. */
  onDateChange(value: string): void {
    if (!value) return;
    this.date.set(value);
    this.selected.set(null);
    this.loadSlots();
  }

  /** Memorizza lo slot scelto (chiamato dai chip). */
  select(slot: Slot | null): void {
    this.selected.set(slot);
  }

  /** Chiede al backend gli slot liberi del giorno scelto. */
  loadSlots(): void {
    this.loading.set(true);
    this.error.set(null);
    this.api.getSlots(this.id, this.date()).subscribe({
      next: (s) => { this.slots.set(s); this.loading.set(false); },
      error: (e: HttpErrorResponse) => {
        this.slots.set([]);
        this.error.set(apiErrorMessage(e, 'Impossibile caricare gli slot'));
        this.loading.set(false);
      },
    });
  }

  /** Invia la prenotazione. Se lo slot è stato preso nel frattempo mostra l'errore del backend. */
  confirm(): void {
    const slot = this.selected();
    if (!slot) return;

    this.saving.set(true);

    this.api.createBooking(this.id, slot.starts_at).subscribe({
      next: () => {
        this.snack.open('Prenotazione confermata ✔', 'Chiudi', { duration: 4000 });
        this.selected.set(null);
        this.saving.set(false);
        this.loadSlots(); // lo slot appena prenotato sparisce dall'elenco
      },
      error: (e: HttpErrorResponse) => {
        this.snack.open(apiErrorMessage(e, 'Prenotazione non riuscita'), 'Chiudi', { duration: 5000 });
        this.selected.set(null);
        this.saving.set(false);
        this.loadSlots(); // aggiorna gli slot: potrebbe essere stato preso da altri
      },
    });
  }

  /** Testo dell'orario di uno slot (HH:mm) senza usare la pipe date.
   *  Se la stringa non è una data valida mostra "??" così lo vediamo subito. */
  label(slot: Slot): string {
    const d = DateTime.fromISO(slot.starts_at);

    if (!d.isValid) return '??';

    return d.toLocaleString(DateTime.TIME_24_SIMPLE);
  }

  /** SOLO DEBUG (poi lo togliamo): mostra il primo slot grezzo come arriva dal backend. */
  firstSlotRaw(): string {
    return JSON.stringify(this.slots()[0]);
  }
}