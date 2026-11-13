import { Component, OnInit, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { DatePipe } from '@angular/common';
import { Subject, debounceTime, distinctUntilChanged, filter, switchMap } from 'rxjs';
import { DateTime } from 'luxon';

import { BookingService } from '../../../core/services/http/booking.service';
import { apiErrorMessage } from '../../../core/services/features/api-error';
import { ConfirmService } from '../../../core/services/features/confirm.service';
import { Booking } from '../../../core/models/models';

import { MatIconModule } from '@angular/material/icon';
import { MatTableModule } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';

@Component({
  selector: 'app-my-bookings',
  standalone: true,
  imports: [
    DatePipe,
    MatIconModule,
    MatTableModule,
    MatButtonModule,
    MatSnackBarModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatPaginatorModule
  ],
  templateUrl: './my-bookings.component.html',
  styleUrl: './my-bookings.component.scss',
})
export class MyBookingsComponent implements OnInit {

  private api = inject(BookingService);
  private snack = inject(MatSnackBar);
  private confirmDialog = inject(ConfirmService);

  private searchSubject = new Subject<string>();

  bookings = signal<Booking[]>([]);
  loading = signal(true);

  // Parametri della tabella
  page = signal(1);
  perPage = signal(10);
  total = signal(0);

  search = signal('');
  status = signal('');

  sortBy = signal<'starts_at' | 'created_at'>('starts_at');
  sortDirection = signal<'asc' | 'desc'>('asc');

  columns = ['bookable', 'date', 'time', 'status', 'actions'];

  ngOnInit(): void {

    // Evita di effettuare una richiesta API per ogni carattere digitato.
    this.searchSubject
      .pipe(
        debounceTime(400),
        distinctUntilChanged()
      )
      .subscribe(() => {
        this.page.set(1);
        this.loadBookings();
      });

    this.loadBookings();
  }

  /** Carica le prenotazioni applicando tutti i parametri. */
  loadBookings(): void {
    this.loading.set(true);

    this.api.getMyBookings({
      page: this.page(),
      perPage: this.perPage(),
      sortBy: this.sortBy(),
      sortDirection: this.sortDirection(),
      search: this.search(),
      status: this.status() as 'confirmed' | 'cancelled' | undefined
    }).subscribe({
      next: (response) => {
        this.bookings.set(response.data);
        this.total.set(response.total);
        this.loading.set(false);
      },
      error: (e: HttpErrorResponse) => {
        this.snack.open(
          apiErrorMessage(e, 'Impossibile caricare le prenotazioni'),
          'Chiudi',
          { duration: 5000 }
        );
        this.loading.set(false);
      }
    });
  }

  /** Aggiorna la ricerca. */
  onSearch(value: string): void {
    this.search.set(value);
    this.searchSubject.next(value);
  }

  /** Cambia il filtro dello stato. */
  onStatusChange(value: string): void {
    this.status.set(value);
    this.page.set(1);
    this.loadBookings();
  }

  /** Cambia l'ordinamento per data. */
  toggleSort(): void {
    this.sortDirection.update(direction =>
      direction === 'asc' ? 'desc' : 'asc'
    );

    this.page.set(1);
    this.loadBookings();
  }

  /** Gestisce il cambio pagina e la dimensione della pagina. */
  onPageChange(event: PageEvent): void {
    this.page.set(event.pageIndex + 1);
    this.perPage.set(event.pageSize);
    this.loadBookings();
  }

  /** Si può annullare solo se è confermata e non è già iniziata. */
  canCancel(b: Booking): boolean {
    if (!b.starts_at) return false;

    const start = DateTime.fromISO(b.starts_at);

    if (!start.isValid) return false;

    return b.status === 'confirmed' &&
      start.toMillis() > DateTime.now().toMillis();
  }

  /** Testo dello stato mostrato nella tabella. */
  statusLabel(b: Booking): string {
    if (b.status === 'cancelled') return 'Annullata';

    if (b.ends_at) {
      const end = DateTime.fromISO(b.ends_at);

      if (end.isValid && end.toMillis() < DateTime.now().toMillis()) {
        return 'Conclusa';
      }
    }

    return 'Confermata';
  }

  /** Chiede conferma, annulla sul server e aggiorna la riga. */
  cancel(b: Booking): void {
    this.confirmDialog
      .confirm({
        title: 'Annullare la prenotazione?',
        message: 'La prenotazione verrà annullata.',
        confirmLabel: 'Annulla prenotazione',
        cancelLabel: 'Mantieni',
      })
      .pipe(
        filter((confirmed) => confirmed),
        switchMap(() => this.api.cancelBooking(b.id))
      )
      .subscribe({
      next: () => {

        this.bookings.update(list =>
          list.map(x =>
            x.id === b.id
              ? { ...x, status: 'cancelled' } as Booking
              : x
          )
        );

        this.snack.open('Prenotazione annullata', 'Chiudi', {
          duration: 4000
        });

      },
      error: (e: HttpErrorResponse) => {
        this.snack.open(
          apiErrorMessage(e, 'Annullamento non riuscito'),
          'Chiudi',
          { duration: 6000 }
        );
      }
    });
  }
}