import { DatePipe } from '@angular/common';
import { Component, inject, signal, OnInit } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { RouterLink } from '@angular/router';
import { MatButtonModule } from '@angular/material/button';
import { Course, SubscriptionUsage } from '../../../core/models/models';
import { apiErrorMessage } from '../../../core/services/features/api-error';
import { CourseService } from '../../../core/services/http/course.service';
import { SubscriptionService } from '../../../core/services/http/subscription.service';
import { CourseCardComponent } from '../../../shared/course-card/course-card.component';

/**
 * "I miei corsi" (/my-courses): il riepilogo dell'abbonamento e i corsi a cui l'utente è iscritto,
 * con la possibilità di annullare l'iscrizione.
 */
@Component({
  selector: 'app-my-courses',
  standalone: true,
  imports: [DatePipe, RouterLink, MatButtonModule, CourseCardComponent],
  templateUrl: './my-courses.component.html',
})
export class MyCoursesComponent implements OnInit {
  private courseApi = inject(CourseService);
  private subscriptionApi = inject(SubscriptionService);

  protected courses = signal<Course[]>([]);
  protected usage = signal<SubscriptionUsage | null>(null);
  protected loading = signal(true);
  protected error = signal<string | null>(null);
  protected notice = signal<string | null>(null);
  protected busyId = signal<number | null>(null);

  /** All'apertura carica i corsi dell'utente e il riepilogo dell'abbonamento. */
  ngOnInit(): void {
    this.reload();
  }

  /** Ricarica corsi e abbonamento (dopo un annullamento il numero di corsi disponibili cambia). */
  private reload(): void {
    this.courseApi.myCourses().subscribe({
      next: (courses) => {
        this.courses.set(courses);
        this.loading.set(false);
      },
      error: (e: HttpErrorResponse) => {
        this.error.set(apiErrorMessage(e, 'Impossibile caricare i tuoi corsi.'));
        this.loading.set(false);
      },
    });

    this.subscriptionApi.mine().subscribe({
      next: (usage) => this.usage.set(usage),
      error: (e: HttpErrorResponse) => this.error.set(apiErrorMessage(e, 'Impossibile caricare il tuo abbonamento.')),
    });
  }

  /** Annulla l'iscrizione al corso e ricarica l'elenco. */
  protected cancel(course: Course): void {
    this.busyId.set(course.id);
    this.error.set(null);
    this.notice.set(null);

    this.courseApi.cancelEnrollment(course.id).subscribe({
      next: () => {
        this.notice.set(`Iscrizione al corso ${course.name} annullata.`);
        this.busyId.set(null);
        this.reload();
      },
      error: (e: HttpErrorResponse) => {
        this.error.set(apiErrorMessage(e, 'Annullamento non riuscito.'));
        this.busyId.set(null);
      },
    });
  }
}
