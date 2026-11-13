import { Injectable, inject } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import { Observable, map } from 'rxjs';
import { ConfirmDialogComponent, ConfirmDialogData } from '../../../shared/confirm-dialog/confirm-dialog.component';

/**
 * Chiede conferma all'utente con una finestra (al posto del `confirm()` del browser), prima di un'azione che non si
 * può disfare: eliminare, annullare... Nei test si sostituisce con un finto servizio che risponde sì o no.
 */
@Injectable({ providedIn: 'root' })
export class ConfirmService {
  private dialog = inject(MatDialog);

  /**
   * Apre la finestra di conferma e restituisce un flusso che emette una sola volta: `true` se l'utente conferma,
   * `false` se rinuncia, preme Esc o clicca fuori dalla finestra.
   */
  confirm(data: ConfirmDialogData): Observable<boolean> {
    return this.dialog
      .open<ConfirmDialogComponent, ConfirmDialogData, boolean>(ConfirmDialogComponent, { data, width: '440px', maxWidth: '95vw' })
      .afterClosed()
      .pipe(map((result) => result === true));
  }
}
