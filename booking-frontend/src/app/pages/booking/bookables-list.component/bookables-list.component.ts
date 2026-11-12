import { Component, inject, signal, OnInit } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { RouterLink } from '@angular/router';

import { AuthService } from '../../../core/services/http/auth.service';
import { BookingService } from '../../../core/services/http/booking.service';
import { Bookable } from '../../../core/models/models';
import { apiErrorMessage } from '../../../core/services/features/api-error';

import { MatCardModule } from '@angular/material/card';
import { MatChipsModule } from '@angular/material/chips';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import {
  MatFormFieldModule
} from '@angular/material/form-field';

import {
  MatInputModule
} from '@angular/material/input';

import {
  MatSelectModule
} from '@angular/material/select';

import {
  MatButtonModule
} from '@angular/material/button';

@Component({
  selector: 'app-bookables-list',
  standalone: true,
  imports: [
    RouterLink,
    MatCardModule,
    MatChipsModule,
    MatPaginatorModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatButtonModule
  ],
  templateUrl: './bookables-list.component.html',
  styleUrl: './bookables-list.component.scss',
})
export class BookablesListComponent implements OnInit {
  private api = inject(BookingService);
  private auth = inject(AuthService);

  // Dati
  bookables = signal<Bookable[]>([]);
  loading = signal(true);
  error = signal<string | null>(null);

  // Paginazione
  page = signal(0);
  pageSize = signal(5);
  total = signal(0);

  // Ordinamento
  sortBy = signal('name');
  sortDirection = signal<'asc' | 'desc'>('asc');

  // Ricerca
  search = signal('');

  ngOnInit(): void {
    this.loadBookables();
  }

  loadBookables(): void {
    this.loading.set(true);
    this.error.set(null);

    this.api.getBookables({
      includeInactive: this.auth.isAdmin(),
      page: this.page() + 1,
      perPage: this.pageSize(),
      sortBy: this.sortBy(),
      sortDirection: this.sortDirection(),
      search: this.search(),
    }).subscribe({
      next: (response) => {
        this.bookables.set(response.data);
        this.total.set(response.total);
        this.page.set(response.current_page - 1);
        this.loading.set(false);
      },
      error: (e: HttpErrorResponse) => {
        this.error.set(
          apiErrorMessage(e, 'Impossibile caricare le sale')
        );
        this.loading.set(false);
      },
    });
  }

  onPageChange(event: PageEvent): void {
    this.page.set(event.pageIndex);
    this.pageSize.set(event.pageSize);
    this.loadBookables();
  }
}