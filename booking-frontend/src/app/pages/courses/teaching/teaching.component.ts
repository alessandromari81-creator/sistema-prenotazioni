import { Component, inject, signal, OnInit } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { Course } from '../../../core/models/models';
import { apiErrorMessage } from '../../../core/services/features/api-error';
import { CourseService } from '../../../core/services/http/course.service';
import { CourseCardComponent } from '../../../shared/course-card/course-card.component';

/**
 * "I corsi che insegno" (/teaching): riservata a chi è istruttore di almeno un corso.
 * Da ogni scheda si apre la pagina del corso, dove l'istruttore vede l'elenco degli iscritti.
 */
@Component({
  selector: 'app-teaching',
  standalone: true,
  imports: [CourseCardComponent],
  templateUrl: './teaching.component.html',
})
export class TeachingComponent implements OnInit {
  private api = inject(CourseService);

  protected courses = signal<Course[]>([]);
  protected loading = signal(true);
  protected error = signal<string | null>(null);

  /** All'apertura carica i corsi che l'utente insegna. */
  ngOnInit(): void {
    this.api.teaching().subscribe({
      next: (courses) => {
        this.courses.set(courses);
        this.loading.set(false);
      },
      error: (e: HttpErrorResponse) => {
        this.error.set(apiErrorMessage(e, 'Impossibile caricare i tuoi corsi.'));
        this.loading.set(false);
      },
    });
  }
}
