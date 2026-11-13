import { Component, inject, signal, OnInit } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { MatButtonModule } from '@angular/material/button';
import { MatDialog } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import { MatTableModule } from '@angular/material/table';
import { User } from '../../../core/models/models';
import { apiErrorMessage } from '../../../core/services/features/api-error';
import { UserService } from '../../../core/services/http/user.service';
import { UserSubscriptionsDialogComponent, UserSubscriptionsDialogData } from './user-subscriptions-dialog.component';

/**
 * Gli utenti e i loro abbonamenti (/admin/subscriptions): elenco paginato con ricerca. Il pulsante di una riga apre una
 * finestra (UserSubscriptionsDialogComponent) dove l'amministratore vede gli abbonamenti, ne assegna uno o ne annulla uno.
 */
@Component({
  selector: 'app-admin-subscriptions',
  standalone: true,
  imports: [MatButtonModule, MatFormFieldModule, MatIconModule, MatInputModule, MatPaginatorModule, MatTableModule],
  templateUrl: './admin-subscriptions.component.html',
})
export class AdminSubscriptionsComponent implements OnInit {
  private userApi = inject(UserService);
  private dialog = inject(MatDialog);
  private searchSubject = new Subject<string>();

  protected users = signal<User[]>([]);
  protected loading = signal(true);
  protected error = signal<string | null>(null);

  protected page = signal(1);
  protected perPage = signal(10);
  protected total = signal(0);
  protected search = signal('');

  /** Le colonne della tabella. */
  protected readonly columns = ['name', 'email', 'actions'];

  /** All'apertura attiva la ricerca "ritardata" (parte 400 ms dopo l'ultimo tasto) e carica gli utenti. */
  ngOnInit(): void {
    this.searchSubject.pipe(debounceTime(400), distinctUntilChanged()).subscribe(() => {
      this.page.set(1);
      this.load();
    });

    this.load();
  }

  /** Carica la pagina corrente degli utenti (in ordine alfabetico), filtrata dalla ricerca per nome o email. */
  protected load(): void {
    this.loading.set(true);
    this.error.set(null);

    this.userApi.list({ page: this.page(), perPage: this.perPage(), search: this.search().trim() }).subscribe({
      next: (response) => {
        this.users.set(response.data);
        this.total.set(response.total);
        this.loading.set(false);
      },
      error: (e: HttpErrorResponse) => {
        this.error.set(apiErrorMessage(e, 'Impossibile caricare gli utenti.'));
        this.loading.set(false);
      },
    });
  }

  /** Gestisce il testo della ricerca. */
  protected onSearch(value: string): void {
    this.search.set(value);
    this.searchSubject.next(value);
  }

  /** Cambio di pagina o di numero di righe per pagina. */
  protected onPageChange(event: PageEvent): void {
    this.page.set(event.pageIndex + 1);
    this.perPage.set(event.pageSize);
    this.load();
  }

  /** Apre la finestra con gli abbonamenti della persona scelta. */
  protected openSubscriptions(user: User): void {
    this.dialog.open<UserSubscriptionsDialogComponent, UserSubscriptionsDialogData>(UserSubscriptionsDialogComponent, {
      data: { user },
      width: '640px',
      maxWidth: '95vw',
      autoFocus: 'dialog', // il focus parte dalla finestra (si legge il titolo), non dal primo campo
    });
  }
}
