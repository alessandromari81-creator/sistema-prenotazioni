import { Component, computed, inject, signal, OnInit } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import { MatSelectModule } from '@angular/material/select';
import { Course } from '../../../core/models/models';
import { apiErrorMessage } from '../../../core/services/features/api-error';
import { NotificationService } from '../../../core/services/features/notification.service';
import { AuthService } from '../../../core/services/http/auth.service';
import { CourseService } from '../../../core/services/http/course.service';
import { WEEK_DAYS } from '../../../core/utils/schedule';
import { CourseCardComponent } from '../../../shared/course-card/course-card.component';

/**
 * L'elenco dei corsi (/courses): ricerca per nome, filtro per giorno, paginazione e iscrizione diretta.
 * Il risultato di ogni azione è annunciato in una zona "live" per chi usa un lettore di schermo.
 */
@Component({
  selector: 'app-courses-list',
  standalone: true,
  imports: [
    CourseCardComponent,
    MatButtonModule,
    MatFormFieldModule,
    MatInputModule,
    MatPaginatorModule,
    MatSelectModule,
  ],
  templateUrl: './courses-list.component.html',
})
export class CoursesListComponent implements OnInit {
  private api = inject(CourseService);
  private notify = inject(NotificationService); // avvisi a comparsa
  private auth = inject(AuthService);

  /** I giorni per il filtro. */
  protected readonly days = WEEK_DAYS;

  // Dati
  protected courses = signal<Course[]>([]);
  protected loading = signal(true);
  protected error = signal<string | null>(null);
  protected busyId = signal<number | null>(null); // il corso su cui è in corso un'azione

  // Paginazione
  protected page = signal(0);
  protected pageSize = signal(6);
  protected total = signal(0);

  // Filtri
  protected search = signal('');
  protected day = signal<number | null>(null);

  /** La frase letta dai lettori di schermo dopo ogni ricerca ("3 corsi trovati"). */
  protected resultsText = computed(() => {
    if (this.loading()) return '';
    const n = this.total();
    return n === 0 ? 'Nessun corso trovato' : `${n} ${n === 1 ? 'corso trovato' : 'corsi trovati'}`;
  });

  /** All'apertura della pagina carica la prima pagina di corsi. */
  ngOnInit(): void {
    this.load();
  }

  /** Carica i corsi con i filtri e la pagina correnti. Gli amministratori vedono anche i corsi disattivati. */
  protected load(): void {
    this.loading.set(true);
    this.error.set(null);

    this.api
      .list({
        page: this.page() + 1,
        perPage: this.pageSize(),
        search: this.search().trim(),
        dayOfWeek: this.day(),
        includeInactive: this.auth.isAdmin(),
      })
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

  /** Riparte dalla prima pagina (dopo un cambio di ricerca o di filtro). */
  protected applyFilters(): void {
    this.page.set(0);
    this.load();
  }

  /** Cambio di pagina o di numero di corsi per pagina. */
  protected onPageChange(event: PageEvent): void {
    this.page.set(event.pageIndex);
    this.pageSize.set(event.pageSize);
    this.load();
  }

  /** Iscrive l'utente al corso e aggiorna la scheda con i dati nuovi (iscritti, posti liberi). */
  protected enroll(course: Course): void {
    this.runAction(course, this.api.enroll(course.id), `Iscrizione al corso ${course.name} completata.`);
  }

  /** Annulla l'iscrizione dell'utente al corso e aggiorna la scheda. */
  protected cancel(course: Course): void {
    this.runAction(course, this.api.cancelEnrollment(course.id), `Iscrizione al corso ${course.name} annullata.`);
  }

  /**
   * Esegue un'azione sull'iscrizione: durante l'attesa blocca i pulsanti di quel corso, poi mostra l'esito
   * e sostituisce la scheda con il corso aggiornato restituito dal server.
   */
  private runAction(
    course: Course,
    request: ReturnType<CourseService['enroll']>,
    successMessage: string
  ): void {
    this.busyId.set(course.id);

    request.subscribe({
      next: (enrollment) => {
        if (enrollment.course) {
          this.courses.update((list) => list.map((c) => (c.id === course.id ? enrollment.course! : c)));
        }
        this.notify.success(successMessage);
        this.busyId.set(null);
      },
      error: (e: HttpErrorResponse) => {
        this.notify.error(apiErrorMessage(e, 'Operazione non riuscita.'));
        this.busyId.set(null);
      },
    });
  }
}
