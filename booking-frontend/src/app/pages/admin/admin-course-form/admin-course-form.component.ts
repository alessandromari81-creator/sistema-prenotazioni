import { Component, inject, signal, OnInit } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { catchError, of, switchMap } from 'rxjs';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatIconModule } from '@angular/material/icon';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { Bookable, Course, CourseInput } from '../../../core/models/models';
import { apiErrorMessage } from '../../../core/services/features/api-error';
import { NotificationService } from '../../../core/services/features/notification.service';
import { BookingService } from '../../../core/services/http/booking.service';
import { CourseService } from '../../../core/services/http/course.service';
import { WEEK_DAYS, shortTime } from '../../../core/utils/schedule';
import { CourseCoverComponent } from '../../../shared/course-cover/course-cover.component';

/** Formati e dimensione massima accettati dall'API per l'immagine di un corso. */
const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const IMAGE_MAX_BYTES = 2 * 1024 * 1024;

/**
 * Form unico per creare (/admin/courses/new) e modificare (/admin/courses/:id/edit) un corso.
 * Le regole di validazione rispecchiano quelle del backend; le regole di business (per esempio i posti
 * minimi in base agli iscritti) le controlla il server e il suo messaggio viene mostrato a video.
 */
@Component({
  selector: 'app-admin-course-form',
  standalone: true,
  imports: [CourseCoverComponent, ReactiveFormsModule, RouterLink, MatButtonModule, MatIconModule, MatFormFieldModule, MatInputModule, MatSelectModule, MatSlideToggleModule],
  templateUrl: './admin-course-form.component.html',
  styleUrl: './admin-course-form.component.scss',
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

  /** Il corso in modifica (serve per mostrare la sua immagine attuale). */
  protected current = signal<Course | null>(null);

  /** Il file scelto dall'admin, in attesa di essere caricato al salvataggio. */
  protected chosenFile = signal<File | null>(null);

  /** L'anteprima (indirizzo temporaneo del browser) del file scelto. */
  protected preview = signal<string | null>(null);

  /** Un errore sul file scelto (formato o dimensione), mostrato accanto al campo. */
  protected imageError = signal<string | null>(null);

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
        this.current.set(course);
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

  /**
   * L'admin sceglie un file: se il formato o la dimensione non vanno lo si dice subito (il server li controlla comunque),
   * altrimenti si tiene da parte e si mostra un'anteprima. Il caricamento vero avviene al salvataggio del corso.
   */
  protected onFileChosen(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    this.imageError.set(null);
    this.clearPreview();

    if (!file) {
      this.chosenFile.set(null);
      return;
    }

    if (!IMAGE_TYPES.includes(file.type)) {
      this.imageError.set("L'immagine deve essere un file JPEG, PNG o WebP.");
    } else if (file.size > IMAGE_MAX_BYTES) {
      this.imageError.set("L'immagine non può superare i 2 MB.");
    }

    if (this.imageError()) {
      this.chosenFile.set(null);
      input.value = '';
      return;
    }

    this.chosenFile.set(file);
    this.preview.set(URL.createObjectURL(file));
  }

  /** Rinuncia al file scelto (non ancora caricato). */
  protected discardChosenFile(input: HTMLInputElement): void {
    input.value = '';
    this.chosenFile.set(null);
    this.imageError.set(null);
    this.clearPreview();
  }

  /** Toglie dal corso l'immagine già caricata, subito (con conferma). */
  protected removeCurrentImage(): void {
    const id = this.courseId;
    if (id === null || !confirm("Togliere l'immagine del corso?")) return;

    this.api.removeImage(id).subscribe({
      next: () => {
        this.current.update((c) => (c ? { ...c, image_url: null } : c));
        this.notify.success("Immagine del corso tolta.");
      },
      error: (e: HttpErrorResponse) => this.notify.error(apiErrorMessage(e, "Impossibile togliere l'immagine.")),
    });
  }

  /** Libera l'indirizzo temporaneo dell'anteprima, se c'è. */
  private clearPreview(): void {
    const url = this.preview();
    if (url) URL.revokeObjectURL(url);
    this.preview.set(null);
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

    request$.pipe(
      // Se è stato scelto un file, dopo il salvataggio del corso lo si carica; l'esito è quello dell'ultimo passo
      switchMap((course) => {
        const file = this.chosenFile();
        if (!file) return of(course);

        // Il corso è già salvato: se solo l'immagine fallisce lo si dice, senza far ripetere (e duplicare) il salvataggio
        return this.api.uploadImage(course.id, file).pipe(
          catchError((e: HttpErrorResponse) => {
            this.notify.error(apiErrorMessage(e, "Corso salvato, ma l'immagine non è stata caricata. Riprova dalla modifica del corso."));
            return of(course);
          })
        );
      })
    ).subscribe({
      next: () => this.router.navigate(['/admin/courses']),
      error: (e: HttpErrorResponse) => {
        this.notify.error(apiErrorMessage(e, 'Salvataggio non riuscito.'));
        this.saving.set(false);
      },
    });
  }
}
