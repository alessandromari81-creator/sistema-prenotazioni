
import { Component, inject, signal, OnInit } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { RouterLink } from '@angular/router';
import { Subject, debounceTime, distinctUntilChanged, filter, switchMap, tap } from 'rxjs';

import { BookingService } from '../../../core/services/http/booking.service';
import { Bookable } from '../../../core/models/models';
import { apiErrorMessage } from '../../../core/services/features/api-error';
import { ConfirmService } from '../../../core/services/features/confirm.service';

import { MatTableModule } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatChipsModule } from '@angular/material/chips';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';

@Component({
  selector: 'app-admin-bookables',
  standalone: true,
  imports: [
    MatTableModule,
    MatButtonModule,
    MatIconModule,
    MatChipsModule,
    MatFormFieldModule,
    MatInputModule,
    MatPaginatorModule,
    RouterLink
  ],
  templateUrl: './admin-bookables.component.html',
  styleUrl: './admin-bookables.component.scss',
})
export class AdminBookablesComponent implements OnInit {

  private api = inject(BookingService);
  private confirmDialog = inject(ConfirmService);
  private searchSubject = new Subject<string>();

  bookables = signal<Bookable[]>([]);
  loading = signal(true);
  error = signal<string | null>(null);

  page = signal(1);
  perPage = signal(10);
  total = signal(0);

  search = signal('');
  sortBy = signal('name');
  sortDirection = signal<'asc' | 'desc'>('asc');

  columns = ['name', 'slot', 'status', 'actions'];

  ngOnInit(): void {

    this.searchSubject.pipe(
      debounceTime(400),
      distinctUntilChanged()
    ).subscribe(() => {
      this.page.set(1);
      this.loadBookables();
    });

    this.loadBookables();
  }

  /** Carica tutte le sale, comprese quelle disattivate. */
  loadBookables(): void {
    this.loading.set(true);
    this.error.set(null);

    this.api.getBookables({
      includeInactive: true,
      page: this.page(),
      perPage: this.perPage(),
      sortBy: this.sortBy(),
      sortDirection: this.sortDirection(),
      search: this.search()
    }).subscribe({
      next: (response) => {
        this.bookables.set(response.data);
        this.total.set(response.total);
        this.loading.set(false);
      },
      error: (e: HttpErrorResponse) => {
        this.error.set(
          apiErrorMessage(e, 'Impossibile caricare le sale')
        );
        this.loading.set(false);
      }
    });
  }

  /** Gestisce la ricerca. */
  onSearch(value: string): void {
    this.search.set(value);
    this.searchSubject.next(value);
  }

  /** Inverte l'ordinamento per nome. */
  toggleSort(): void {
    this.sortDirection.update(direction =>
      direction === 'asc' ? 'desc' : 'asc'
    );

    this.page.set(1);
    this.loadBookables();
  }

  /** Gestisce la paginazione. */
  onPageChange(event: PageEvent): void {
    this.page.set(event.pageIndex + 1);
    this.perPage.set(event.pageSize);
    this.loadBookables();
  }

  /** Elimina una sala e aggiorna la tabella. */
  remove(b: Bookable): void {
    this.confirmDialog
      .confirm({
        title: 'Eliminare la sala?',
        message: `La sala "${b.name}" verrà eliminata, con i suoi orari e tutte le sue prenotazioni.`,
        confirmLabel: 'Elimina sala',
      })
      .pipe(
        filter((confirmed) => confirmed),
        tap(() => this.error.set(null)),
        switchMap(() => this.api.deleteBookable(b.id))
      )
      .subscribe({
      next: () => {
        this.total.update(total => total - 1);

        // Se era l'ultimo elemento della pagina, torna alla precedente.
        if (this.bookables().length === 1 && this.page() > 1) {
          this.page.update(page => page - 1);
        }

        this.loadBookables();
      },
      error: (e: HttpErrorResponse) => {
        this.error.set(
          apiErrorMessage(e, 'Eliminazione non riuscita')
        );
      }
    });
  }
}
