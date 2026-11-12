import { Component, inject, signal, OnInit } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { RouterLink } from '@angular/router';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import { MatTableModule } from '@angular/material/table';
import { Course } from '../../../core/models/models';
import { apiErrorMessage } from '../../../core/services/features/api-error';
import { NotificationService } from '../../../core/services/features/notification.service';
import { CourseService } from '../../../core/services/http/course.service';
import { courseSchedule } from '../../../core/utils/schedule';

/**
 * Gestione dei corsi per gli amministratori (/admin/courses): elenco completo (anche i corsi disattivati)
 * con ricerca, e le azioni di modifica, gestione degli istruttori ed eliminazione.
 */
@Component({
  selector: 'app-admin-courses',
  standalone: true,
  imports: [RouterLink, MatButtonModule, MatFormFieldModule, MatIconModule, MatInputModule, MatPaginatorModule, MatTableModule],
  templateUrl: './admin-courses.component.html',
})
export class AdminCoursesComponent implements OnInit {
  private api = inject(CourseService);
  private notify = inject(NotificationService); // avvisi a comparsa
  private searchSubject = new Subject<string>();

  protected courses = signal<Course[]>([]);
  protected loading = signal(true);
  protected error = signal<string | null>(null);

  protected page = signal(1);
  protected perPage = signal(10);
  protected total = signal(0);
  protected search = signal('');

  /** Le colonne della tabella. */
  protected readonly columns = ['name', 'when', 'seats', 'status', 'actions'];

  /** All'apertura attiva la ricerca "ritardata" (parte 400 ms dopo l'ultimo tasto) e carica i corsi. */
  ngOnInit(): void {
    this.searchSubject.pipe(debounceTime(400), distinctUntilChanged()).subscribe(() => {
      this.page.set(1);
      this.load();
    });

    this.load();
  }

  /** Quando si tiene un corso, per esempio "Lunedì dalle 18:30 alle 19:30". */
  protected when(c: Course): string {
    return courseSchedule(c.day_of_week, c.start_time, c.duration_minutes);
  }

  /** Carica i corsi, compresi quelli disattivati. */
  protected load(): void {
    this.loading.set(true);
    this.error.set(null);

    this.api
      .list({ page: this.page(), perPage: this.perPage(), search: this.search().trim(), includeInactive: true })
      .subscribe({
        next: (response) => {
          this.courses.set(response.data);
          this.total.set(response.total);
          this.loading.set(false);
        },
        error: (e: HttpErrorResponse) => {
          this.error.set(apiErrorMessage(e, 'Impossibile caricare i corsi.'));
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

  /** Elimina un corso dopo conferma. Il server rifiuta se ci sono iscritti attivi: il motivo si mostra a video. */
  protected remove(course: Course): void {
    if (!confirm(`Eliminare il corso "${course.name}"?`)) return;


    this.api.delete(course.id).subscribe({
      next: () => {
        this.notify.success(`Il corso ${course.name} è stato eliminato.`);
        if (this.courses().length === 1 && this.page() > 1) this.page.update((p) => p - 1);
        this.load();
      },
      error: (e: HttpErrorResponse) => this.notify.error(apiErrorMessage(e, 'Eliminazione non riuscita.')),
    });
  }
}
