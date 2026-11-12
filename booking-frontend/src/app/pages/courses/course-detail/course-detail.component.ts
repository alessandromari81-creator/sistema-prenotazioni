import { Component, computed, inject, signal, OnInit } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatTableModule } from '@angular/material/table';
import { Course, Enrollment } from '../../../core/models/models';
import { apiErrorMessage } from '../../../core/services/features/api-error';
import { NotificationService } from '../../../core/services/features/notification.service';
import { AuthService } from '../../../core/services/http/auth.service';
import { CourseService } from '../../../core/services/http/course.service';
import { courseSchedule } from '../../../core/utils/schedule';

/**
 * La pagina di un corso (/courses/:id): dettagli, iscrizione e annullamento.
 * Gli amministratori e gli istruttori del corso vedono anche l'elenco degli iscritti.
 */
@Component({
  selector: 'app-course-detail',
  standalone: true,
  imports: [RouterLink, MatButtonModule, MatIconModule, MatTableModule],
  templateUrl: './course-detail.component.html',
})
export class CourseDetailComponent implements OnInit {
  private api = inject(CourseService);
  private notify = inject(NotificationService); // avvisi a comparsa
  private route = inject(ActivatedRoute);
  protected auth = inject(AuthService);

  protected course = signal<Course | null>(null);
  protected students = signal<Enrollment[]>([]);
  protected loading = signal(true);
  protected busy = signal(false);
  protected error = signal<string | null>(null);

  /** Le colonne della tabella degli iscritti. */
  protected readonly columns = ['name', 'email'];

  /** Quando si tiene il corso, per esempio "Lunedì dalle 18:30 alle 19:30". */
  protected schedule = computed(() => {
    const c = this.course();
    return c ? courseSchedule(c.day_of_week, c.start_time, c.duration_minutes) : '';
  });

  /** Chi può vedere gli iscritti: gli amministratori e gli istruttori di questo corso. */
  protected canSeeStudents = computed(() => this.auth.isAdmin() || this.course()?.is_instructor === true);

  /** Gli istruttori scritti a parole, per esempio "Istruttore: Luca Verdi" (vuoto se non ce ne sono). */
  protected instructorsText = computed(() => {
    const list = this.course()?.instructors ?? [];
    if (!list.length) return '';
    return `${list.length === 1 ? 'Istruttore' : 'Istruttori'}: ${list.map((i) => i.name).join(', ')}`;
  });

  /** Il testo sui posti liberi ("Al completo", "5 posti liberi"). */
  protected spotsText = computed(() => {
    const left = this.course()?.spots_left;
    if (left === undefined) return '';
    if (left === 0) return 'Al completo';
    return `${left} ${left === 1 ? 'posto libero' : 'posti liberi'}`;
  });

  /** All'apertura legge l'id dall'indirizzo e carica il corso. */
  ngOnInit(): void {
    const id = Number(this.route.snapshot.paramMap.get('id'));

    this.api.get(id).subscribe({
      next: (course) => {
        this.course.set(course);
        this.loading.set(false);
        if (this.canSeeStudents()) this.loadStudents(course.id);
      },
      error: (e: HttpErrorResponse) => {
        this.error.set(apiErrorMessage(e, 'Corso non trovato.'));
        this.loading.set(false);
      },
    });
  }

  /** Iscrive l'utente al corso e ricarica i dati. */
  protected enroll(): void {
    this.act(() => this.api.enroll(this.course()!.id), 'Iscrizione completata.');
  }

  /** Annulla l'iscrizione dell'utente al corso e ricarica i dati. */
  protected cancel(): void {
    this.act(() => this.api.cancelEnrollment(this.course()!.id), 'Iscrizione annullata.');
  }

  /** Carica l'elenco degli iscritti (solo per chi ha il permesso di vederlo). */
  private loadStudents(courseId: number): void {
    this.api.students(courseId).subscribe({
      next: (students) => this.students.set(students),
      error: (e: HttpErrorResponse) => this.error.set(apiErrorMessage(e, 'Impossibile caricare gli iscritti.')),
    });
  }

  /**
   * Esegue un'azione sull'iscrizione: blocca i pulsanti durante l'attesa, mostra l'esito e rilegge il corso (il server
   * risponde 204 senza corpo, quindi iscritti, posti liberi e "sono iscritto?" si chiedono con una lettura).
   */
  private act(request: () => ReturnType<CourseService['enroll']>, successMessage: string): void {
    this.busy.set(true);

    request().subscribe({
      next: () => {
        this.notify.success(successMessage);
        this.refresh();
      },
      error: (e: HttpErrorResponse) => {
        this.notify.error(apiErrorMessage(e, 'Operazione non riuscita.'));
        this.busy.set(false);
      },
    });
  }

  /**
   * Rilegge il corso con i dati aggiornati (e, se l'utente può vederlo, l'elenco degli iscritti). Alla fine sblocca i
   * pulsanti. Se la lettura fallisce lo dice in un avviso: l'azione era comunque riuscita.
   */
  private refresh(): void {
    const id = this.course()!.id;

    this.api.get(id).subscribe({
      next: (course) => {
        this.course.set(course);
        if (this.canSeeStudents()) this.loadStudents(course.id);
        this.busy.set(false);
      },
      error: (e: HttpErrorResponse) => {
        this.notify.error(apiErrorMessage(e, 'Impossibile aggiornare i dati del corso.'));
        this.busy.set(false);
      },
    });
  }
}
