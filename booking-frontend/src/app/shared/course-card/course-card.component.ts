import { Component, computed, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { Course } from '../../core/models/models';
import { CourseCoverComponent } from '../course-cover/course-cover.component';
import { courseSchedule } from '../../core/utils/schedule';

/**
 * La scheda di un corso, usata in più pagine (elenco, i miei corsi, i corsi che insegno).
 * Mostra nome, quando si tiene, istruttori e uno stato scritto a parole (posti liberi, "Sei iscritto"...).
 * I pulsanti d'azione li mette la pagina che la usa, nello spazio indicato con l'attributo "actions".
 */
@Component({
  selector: 'app-course-card',
  standalone: true,
  imports: [CourseCoverComponent, RouterLink, MatButtonModule, MatCardModule, MatIconModule],
  templateUrl: './course-card.component.html',
  styleUrl: './course-card.component.scss',
})
export class CourseCardComponent {
  /** Il corso da mostrare. */
  readonly course = input.required<Course>();

  /** Se mostrare il collegamento alla pagina di dettaglio del corso. */
  readonly showDetailLink = input(true);

  /** Quando si tiene il corso, per esempio "Lunedì dalle 18:30 alle 19:30". */
  protected readonly schedule = computed(() => {
    const c = this.course();
    return courseSchedule(c.day_of_week, c.start_time, c.duration_minutes);
  });

  /** L'identificativo del titolo, a cui punta aria-labelledby della scheda. */
  protected readonly titleId = computed(() => `course-title-${this.course().id}`);

  /** I nomi degli istruttori separati da virgola (stringa vuota se non ce ne sono). */
  protected readonly instructorNames = computed(() =>
    (this.course().instructors ?? []).map((i) => i.name).join(', ')
  );

  /** Il testo sui posti: "Al completo", "1 posto libero", "5 posti liberi" (vuoto se il dato manca). */
  protected readonly spotsText = computed(() => {
    const left = this.course().spots_left;

    if (left === undefined) return '';
    if (left === 0) return 'Al completo';
    return `${left} ${left === 1 ? 'posto libero' : 'posti liberi'}`;
  });
}
