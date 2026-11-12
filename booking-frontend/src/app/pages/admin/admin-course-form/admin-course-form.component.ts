import { Component, inject, signal, OnInit } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { Bookable, CourseInput } from '../../../core/models/models';
import { apiErrorMessage } from '../../../core/services/features/api-error';
import { NotificationService } from '../../../core/services/features/notification.service';
import { BookingService } from '../../../core/services/http/booking.service';
import { CourseService } from '../../../core/services/http/course.service';
import { WEEK_DAYS, shortTime } from '../../../core/utils/schedule';

/**
 * Form unico per creare (/admin/courses/new) e modificare (/admin/courses/:id/edit) un corso.
 * Le regole di validazione rispecchiano quelle del backend; le regole di business (per esempio i posti
 * minimi in base agli iscritti) le controlla il server e il suo messaggio viene mostrato a video.
 */
@Component({
  selector: 'app-admin-course-form',
  standalone: true,
  imports: [ReactiveFormsModule, RouterLink, MatButtonModule, MatFormFieldModule, MatInputModule, MatSelectModule, MatSlideToggleModule],
  templateUrl: './admin-course-form.component.html',
})
export class AdminCourseFormComponent implements OnInit {
  private fb = inject(FormBuilder);
  private notify = inject(NotificationService); // avvisi a comparsa
  private api = inject(CourseService);
  private bookingApi = inject(BookingService);
  private route = inject(ActivatedRoute);
  private router = inject(Router);

  /** I giorni per il menu a tendina. */
  protected readonly days = WEEK_DAYS;

  /** null = si sta creando un corso; un numero = si sta modificando quel corso. */
  protected courseId: number | null = null;

  protected loading = signal(false);
  protected saving = signal(false);
  protected error = signal<string | null>(null);
  protected rooms = signal<Bookable[]>([]);

  /** Il form: ogni campo con le sue regole (obbligatorio, limiti). */
  protected form = this.fb.group({
    name: this.fb.nonNullable.control('', [Validators.required, Validators.maxLength(255)]),
    description: this.fb.nonNullable.control('', [Validators.maxLength(2000)]),
    capacity: this.fb.nonNullable.control(12, [Validators.required, Validators.min(1), Validators.max(200)]),
    day_of_week: this.fb.nonNullable.control(1, [Validators.required]),
    start_time: this.fb.nonNullable.control('18:00', [Validators.required]),
    duration_minutes: this.fb.nonNullable.control(60, [Validators.required, Validators.min(15), Validators.max(240)]),
    bookable_id: this.fb.control<number | null>(null),
    is_active: this.fb.nonNullable.control(true),
  });

  /** Carica le sale tra cui scegliere e, se nell'indirizzo c'è un id, il corso da modificare. */
  ngOnInit(): void {
    this.bookingApi.getBookables({ includeInactive: true, perPage: 100 }).subscribe({
      next: (response) => this.rooms.set(response.data),
      error: () => this.rooms.set([]), // le sale sono facoltative: senza, si può comunque salvare il corso
    });

    const idParam = this.route.snapshot.paramMap.get('id');
    if (!idParam) return;

    this.courseId = Number(idParam);
    this.loading.set(true);

    this.api.get(this.courseId).subscribe({
      next: (course) => {
        this.form.patchValue({
          name: course.name,
          description: course.description ?? '',
          capacity: course.capacity,
          day_of_week: course.day_of_week,
          start_time: shortTime(course.start_time),
          duration_minutes: course.duration_minutes,
          bookable_id: course.bookable_id,
          is_active: course.is_active,
        });
        this.loading.set(false);
      },
      error: (e: HttpErrorResponse) => {
        this.error.set(apiErrorMessage(e, 'Impossibile caricare il corso.'));
        this.loading.set(false);
      },
    });
  }

  /** Invia il form: crea o modifica a seconda del caso e torna all'elenco. Gli errori del server si mostrano a video. */
  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.saving.set(true);

    const raw = this.form.getRawValue();
    const payload: CourseInput = { ...raw, description: raw.description || null };

    const request$ = this.courseId === null ? this.api.create(payload) : this.api.update(this.courseId, payload);

    request$.subscribe({
      next: () => this.router.navigate(['/admin/courses']),
      error: (e: HttpErrorResponse) => {
        this.notify.error(apiErrorMessage(e, 'Salvataggio non riuscito.'));
        this.saving.set(false);
      },
    });
  }
}
