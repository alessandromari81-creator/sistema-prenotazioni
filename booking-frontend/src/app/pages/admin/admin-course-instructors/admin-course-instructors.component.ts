import { Component, inject, signal, OnInit } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { Course, User } from '../../../core/models/models';
import { apiErrorMessage } from '../../../core/services/features/api-error';
import { CourseService } from '../../../core/services/http/course.service';
import { UserService } from '../../../core/services/http/user.service';

/**
 * Gli istruttori di un corso (/admin/courses/:id/instructors): l'amministratore vede chi insegna,
 * cerca un utente per nome o email e lo assegna, oppure lo toglie.
 * Se l'utente è iscritto al corso come studente il server rifiuta l'assegnazione e il motivo si mostra a video.
 */
@Component({
  selector: 'app-admin-course-instructors',
  standalone: true,
  imports: [RouterLink, MatButtonModule, MatFormFieldModule, MatIconModule, MatInputModule],
  templateUrl: './admin-course-instructors.component.html',
})
export class AdminCourseInstructorsComponent implements OnInit {
  private courseApi = inject(CourseService);
  private userApi = inject(UserService);
  private route = inject(ActivatedRoute);

  protected course = signal<Course | null>(null);
  protected results = signal<User[]>([]);
  protected searched = signal(false); // vero dopo la prima ricerca (per distinguere "nessun risultato" da "non ho ancora cercato")
  protected query = signal('');
  protected loading = signal(true);
  protected error = signal<string | null>(null);
  protected notice = signal<string | null>(null);

  /** All'apertura legge l'id dall'indirizzo e carica il corso con i suoi istruttori. */
  ngOnInit(): void {
    const id = Number(this.route.snapshot.paramMap.get('id'));

    this.courseApi.get(id).subscribe({
      next: (course) => {
        this.course.set(course);
        this.loading.set(false);
      },
      error: (e: HttpErrorResponse) => {
        this.error.set(apiErrorMessage(e, 'Corso non trovato.'));
        this.loading.set(false);
      },
    });
  }

  /** Cerca gli utenti per nome o email (solo quelli che hanno già fatto almeno un accesso). */
  protected searchUsers(): void {
    this.error.set(null);

    this.userApi.list({ search: this.query().trim(), perPage: 10 }).subscribe({
      next: (response) => {
        this.results.set(response.data);
        this.searched.set(true);
      },
      error: (e: HttpErrorResponse) => this.error.set(apiErrorMessage(e, 'Ricerca non riuscita.')),
    });
  }

  /** Dice se un utente è già tra gli istruttori del corso. */
  protected isInstructor(user: User): boolean {
    return (this.course()?.instructors ?? []).some((i) => i.id === user.id);
  }

  /** Assegna l'utente come istruttore e aggiorna il corso con l'elenco nuovo. */
  protected add(user: User): void {
    this.change(this.courseApi.addInstructor(this.course()!.id, user.id), `${user.name} è ora istruttore del corso.`);
  }

  /** Toglie l'utente dagli istruttori e aggiorna il corso. */
  protected remove(id: number, name: string): void {
    this.change(this.courseApi.removeInstructor(this.course()!.id, id), `${name} non è più istruttore del corso.`);
  }

  /** Esegue la modifica, mostra l'esito e sostituisce il corso con quello aggiornato restituito dal server. */
  private change(request: ReturnType<CourseService['addInstructor']>, successMessage: string): void {
    this.error.set(null);
    this.notice.set(null);

    request.subscribe({
      next: (course) => {
        this.course.set(course);
        this.notice.set(successMessage);
      },
      error: (e: HttpErrorResponse) => this.error.set(apiErrorMessage(e, 'Operazione non riuscita.')),
    });
  }
}
