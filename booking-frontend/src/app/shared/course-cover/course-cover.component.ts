import { Component, computed, input } from '@angular/core';
import { MatIconModule } from '@angular/material/icon';
import { Course } from '../../core/models/models';

/** Le icone e le sfumature di ripiego: il corso ne riceve una in base al suo id, sempre la stessa. */
const FALLBACK_ICONS = ['fitness_center', 'self_improvement', 'directions_run', 'sports_martial_arts', 'pool', 'sports_gymnastics'];
const FALLBACK_TONES = 6;

/**
 * L'immagine di copertina di un corso. Se l'admin ne ha caricata una la mostra; altrimenti disegna un'immagine di ripiego
 * (sfumatura di colore con un'icona sportiva) così le schede hanno sempre lo stesso aspetto.
 * È decorativa (alt vuoto): il nome del corso sta già scritto accanto, quindi un lettore di schermo non la legge.
 */
@Component({
  selector: 'app-course-cover',
  standalone: true,
  imports: [MatIconModule],
  templateUrl: './course-cover.component.html',
  styleUrl: './course-cover.component.scss',
})
export class CourseCoverComponent {
  /** Il corso di cui mostrare la copertina. */
  readonly course = input.required<Pick<Course, 'id' | 'image_url'>>();

  /** L'indirizzo dell'immagine caricata, o null se il corso non ne ha. */
  protected readonly imageUrl = computed(() => this.course().image_url ?? null);

  /** L'icona del ripiego, scelta dall'id del corso. */
  protected readonly icon = computed(() => FALLBACK_ICONS[this.course().id % FALLBACK_ICONS.length]);

  /** La classe della sfumatura del ripiego (tone-0 ... tone-5), scelta dall'id del corso. */
  protected readonly toneClass = computed(() => `tone-${this.course().id % FALLBACK_TONES}`);
}
