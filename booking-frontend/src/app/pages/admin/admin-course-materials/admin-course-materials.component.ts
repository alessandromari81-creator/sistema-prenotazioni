import { Component, inject, signal, OnInit } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { filter, switchMap } from 'rxjs';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { Course, CourseMaterial } from '../../../core/models/models';
import { apiErrorMessage } from '../../../core/services/features/api-error';
import { ConfirmService } from '../../../core/services/features/confirm.service';
import { FileSaverService } from '../../../core/services/features/file-saver.service';
import { NotificationService } from '../../../core/services/features/notification.service';
import { CourseService } from '../../../core/services/http/course.service';
import { formatFileSize } from '../../../core/utils/files';

/** Dimensione massima accettata dall'API per un PDF (10 MB). */
const PDF_MAX_BYTES = 10 * 1024 * 1024;

/**
 * I materiali PDF di un corso (/admin/courses/:id/materials): l'amministratore vede quelli caricati,
 * ne carica di nuovi (titolo + file PDF) e li elimina. Gli iscritti li scaricano dalla pagina del corso.
 */
@Component({
  selector: 'app-admin-course-materials',
  standalone: true,
  imports: [RouterLink, MatButtonModule, MatFormFieldModule, MatIconModule, MatInputModule],
  templateUrl: './admin-course-materials.component.html',
  styleUrl: './admin-course-materials.component.scss',
})
export class AdminCourseMaterialsComponent implements OnInit {
  private api = inject(CourseService);
  private notify = inject(NotificationService); // avvisi a comparsa
  private confirmDialog = inject(ConfirmService);
  private fileSaver = inject(FileSaverService);
  private route = inject(ActivatedRoute);

  protected course = signal<Course | null>(null);
  protected materials = signal<CourseMaterial[]>([]);
  protected loading = signal(true);
  protected saving = signal(false);
  protected busyId = signal<number | null>(null); // il materiale che si sta aprendo o scaricando
  protected error = signal<string | null>(null);

  protected title = signal('');
  protected chosenFile = signal<File | null>(null);
  protected fileError = signal<string | null>(null);

  /** Scrive la dimensione di un file a parole (per il template). */
  protected readonly fileSize = formatFileSize;

  /** All'apertura legge l'id dall'indirizzo e carica il corso e i suoi materiali. */
  ngOnInit(): void {
    const id = Number(this.route.snapshot.paramMap.get('id'));

    this.api.get(id).subscribe({
      next: (course) => {
        this.course.set(course);
        this.loadMaterials(course.id);
      },
      error: (e: HttpErrorResponse) => {
        this.error.set(apiErrorMessage(e, 'Corso non trovato.'));
        this.loading.set(false);
      },
    });
  }

  /** Carica l'elenco dei materiali del corso. */
  private loadMaterials(courseId: number): void {
    this.api.materials(courseId).subscribe({
      next: (list) => {
        this.materials.set(list);
        this.loading.set(false);
      },
      error: (e: HttpErrorResponse) => {
        this.error.set(apiErrorMessage(e, 'Impossibile caricare i materiali.'));
        this.loading.set(false);
      },
    });
  }

  /** L'admin sceglie un file: se non è un PDF o è troppo grande lo si dice subito (il server controlla comunque). */
  protected onFileChosen(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    this.fileError.set(null);
    this.chosenFile.set(null);
    if (!file) return;

    if (file.type !== 'application/pdf') {
      this.fileError.set('Il file deve essere un PDF.');
    } else if (file.size > PDF_MAX_BYTES) {
      this.fileError.set('Il file non può superare i 10 MB.');
    }

    if (this.fileError()) {
      input.value = '';
      return;
    }

    this.chosenFile.set(file);
  }

  /** Carica il materiale (titolo + file) e lo aggiunge all'elenco. Gli errori del server si mostrano in un avviso. */
  protected upload(fileInput: HTMLInputElement): void {
    const file = this.chosenFile();
    const title = this.title().trim();

    if (!title) {
      this.notify.error('Scrivi un titolo per il materiale.');
      return;
    }
    if (!file) {
      this.notify.error('Scegli un file PDF da caricare.');
      return;
    }

    this.saving.set(true);

    this.api.uploadMaterial(this.course()!.id, title, file).subscribe({
      next: (material) => {
        this.materials.update((list) => [...list, material]);
        this.notify.success(`Materiale "${material.title}" caricato.`);
        this.title.set('');
        this.chosenFile.set(null);
        fileInput.value = '';
        this.saving.set(false);
      },
      error: (e: HttpErrorResponse) => {
        this.notify.error(apiErrorMessage(e, 'Caricamento non riuscito.'));
        this.saving.set(false);
      },
    });
  }

  /**
   * Apre il PDF nel visualizzatore del browser, in una scheda nuova (aperta subito al clic, altrimenti il browser la
   * bloccherebbe). Il file si chiede all'API con il token, perché non ha un indirizzo pubblico.
   */
  protected view(material: CourseMaterial): void {
    const tab = window.open('', '_blank');
    this.fetchFile(material, (blob) => this.fileSaver.show(blob, tab, `${material.title}.pdf`), () => tab?.close());
  }

  /** Scarica il PDF come file. */
  protected download(material: CourseMaterial): void {
    this.fetchFile(material, (blob) => this.fileSaver.save(blob, `${material.title}.pdf`));
  }

  /** Chiede il file all'API; poi lo passa a $onFile. In caso di errore avvisa e chiama $onError (per esempio per chiudere la scheda). */
  private fetchFile(material: CourseMaterial, onFile: (blob: Blob) => void, onError: () => void = () => {}): void {
    this.busyId.set(material.id);

    this.api.materialFile(material.course_id, material.id).subscribe({
      next: (blob) => {
        onFile(blob);
        this.busyId.set(null);
      },
      error: () => {
        onError();
        this.notify.error('Impossibile aprire il file.');
        this.busyId.set(null);
      },
    });
  }

  /** Elimina un materiale (con conferma). */
  protected remove(material: CourseMaterial): void {
    this.confirmDialog
      .confirm({
        title: 'Eliminare il materiale?',
        message: `Il file "${material.title}" verrà eliminato.`,
        confirmLabel: 'Elimina materiale',
      })
      .pipe(
        filter((confirmed) => confirmed),
        switchMap(() => this.api.deleteMaterial(material.course_id, material.id))
      )
      .subscribe({
      next: () => {
        this.materials.update((list) => list.filter((m) => m.id !== material.id));
        this.notify.success(`Materiale "${material.title}" eliminato.`);
      },
      error: (e: HttpErrorResponse) => this.notify.error(apiErrorMessage(e, 'Impossibile eliminare il materiale.')),
    });
  }
}
