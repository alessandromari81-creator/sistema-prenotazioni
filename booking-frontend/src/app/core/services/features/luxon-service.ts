import { Injectable } from '@angular/core';
import { DateTime } from 'luxon';

@Injectable({
  providedIn: 'root'
})
export class LuxonService {

  /** Converte DateTime in stringa AAAA-MM-GG */
  toLocalString(date: DateTime): string {
    return date.toISODate() ?? '';
  }

  /** Converte una stringa AAAA-MM-GG in DateTime */
  fromLocalString(value: string): DateTime {
    return DateTime.fromISO(value);
  }

  /** Data di oggi nel formato AAAA-MM-GG */
  todayLocal(): string {
    return DateTime.local().toISODate() ?? '';
  }

}