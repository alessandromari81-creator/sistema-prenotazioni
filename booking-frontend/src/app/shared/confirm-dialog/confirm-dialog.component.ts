import { Component, inject } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';

/** Cosa mostra la finestra di conferma. */
export interface ConfirmDialogData {
  /** Il titolo: la domanda in breve ("Annullare l'abbonamento?"). */
  title: string;
  /** La spiegazione di cosa succede se si conferma. */
  message: string;
  /** Il testo del pulsante che conferma: dice l'azione ("Annulla abbonamento"), mai un generico "Sì". */
  confirmLabel: string;
  /** Il testo del pulsante che rinuncia (predefinito "Annulla"). */
  cancelLabel?: string;
}

/**
 * La finestra "Sei sicuro?" al posto del `confirm()` del browser: si adatta al tema, si legge con i lettori di schermo
 * (ruolo "dialog", titolo collegato) e si chiude con Esc. Si apre con `ConfirmService`, non direttamente.
 * Il focus parte dal pulsante che rinuncia: un Invio dato per sbaglio non compie un'azione distruttiva.
 */
@Component({
  selector: 'app-confirm-dialog',
  standalone: true,
  imports: [MatButtonModule, MatDialogModule],
  template: `
    <h2 mat-dialog-title>{{ data.title }}</h2>

    <mat-dialog-content>
      <p class="message">{{ data.message }}</p>
    </mat-dialog-content>

    <mat-dialog-actions align="end">
      <button mat-button type="button" cdkFocusInitial [mat-dialog-close]="false">{{ data.cancelLabel ?? 'Annulla' }}</button>
      <button mat-flat-button type="button" [mat-dialog-close]="true">{{ data.confirmLabel }}</button>
    </mat-dialog-actions>
  `,
  styles: `.message { margin: 0; }`,
})
export class ConfirmDialogComponent {
  protected data = inject<ConfirmDialogData>(MAT_DIALOG_DATA);
}
