import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '../../../../environments/environment';
import { Availability, AvailabilityInput, Bookable, BookableInput, Booking, Slot } from '../../models/models';
import { PaginatedResponse } from '../../interfaces/paginate-response.interface';

// Unico punto in cui l'app parla con le API di sale, slot e prenotazioni
@Injectable({ providedIn: 'root' })
export class BookingService {
  private http = inject(HttpClient);
  private base = environment.apiUrl;

    /**
   * Elenco delle sale (accetta sia un array sia { data: [...] }).
   * Con includeInactive = true chiede anche le disattivate (il backend lo concede solo all'admin).
   */
  getBookables(options: {
    includeInactive?: boolean;
    page?: number;
    perPage?: number;
    sortBy?: string;
    sortDirection?: 'asc' | 'desc';
    search?: string;
  } = {}): Observable<PaginatedResponse<Bookable>> {

    const params: Record<string, string> = {
      page: String(options.page ?? 1),
      per_page: String(options.perPage ?? 5),
      sort_by: options.sortBy ?? 'name',
      sort_direction: options.sortDirection ?? 'asc',
    };

    if (options.includeInactive) {
      params['all'] = '1';
    }

    if (options.search?.trim()) {
      params['search'] = options.search.trim();
    }

    return this.http.get<PaginatedResponse<Bookable>>(
      `${this.base}/bookables`,
      { params }
    );
  }

  /** Dettaglio di una sala (accetta sia l'oggetto diretto sia { data: {...} }). */
  getBookable(id: number): Observable<Bookable> {
    return this.http.get<any>(`${this.base}/bookables/${id}`).pipe(map((r) => r.data ?? r));
  }

  /** Crea una nuova sala (solo admin: il backend risponde 403 agli altri). */
  createBookable(data: BookableInput): Observable<Bookable> {
    return this.http.post<Bookable>(`${this.base}/bookables`, data);
  }

  /** Modifica una sala esistente (solo admin). */
  updateBookable(id: number, data: BookableInput): Observable<Bookable> {
    return this.http.put<Bookable>(`${this.base}/bookables/${id}`, data);
  }

  /** Elimina una sala con le sue disponibilità e prenotazioni (solo admin). */
  deleteBookable(id: number): Observable<void> {
    return this.http.delete<void>(`${this.base}/bookables/${id}`);
  }

  /** Aggiunge una fascia oraria a una sala (solo admin; il backend rifiuta le sovrapposizioni con 422). */
  addAvailability(bookableId: number, data: AvailabilityInput): Observable<Availability> {
    return this.http.post<Availability>(`${this.base}/bookables/${bookableId}/availabilities`, data);
  }

  /** Elimina una fascia oraria (solo admin). */
  deleteAvailability(id: number): Observable<void> {
    return this.http.delete<void>(`${this.base}/availabilities/${id}`);
  }

  /** Slot liberi di una sala in un giorno (date nel formato AAAA-MM-GG). */
  getSlots(id: number, date: string): Observable<Slot[]> {
    return this.http.get<Slot[]>(`${this.base}/bookables/${id}/slots`, { params: { date } });
  }

  /** Crea una prenotazione: il backend verifica che lo slot sia ancora libero. */
  createBooking(bookableId: number, startsAt: string): Observable<any> {
    return this.http.post<any>(`${this.base}/bookings`, {
      bookable_id: bookableId,
      starts_at: startsAt,
    });
  }

  /** Prenotazioni dell'utente (accetta sia un array sia { data: [...] }). */
  /**
   * Elenco delle prenotazioni con ricerca, filtri, ordinamento e paginazione.
   * L'API restituisce una risposta PaginatedResponse.
    */
  getMyBookings(options: {
    page?: number;
    perPage?: number;
    sortBy?: 'starts_at' | 'created_at';
    sortDirection?: 'asc' | 'desc';
    search?: string;
    status?: 'confirmed' | 'cancelled';
  } = {}): Observable<PaginatedResponse<Booking>> {

    const params: Record<string, string> = {
      page: String(options.page ?? 1),
      per_page: String(options.perPage ?? 10),
      sort_by: options.sortBy ?? 'starts_at',
      sort_direction: options.sortDirection ?? 'asc',
    };

    if (options.search?.trim()) {
      params['search'] = options.search.trim();
    }

    if (options.status) {
      params['status'] = options.status;
    }

    return this.http.get<PaginatedResponse<Booking>>(
      `${this.base}/bookings`,
      { params }
    );
  }

  /** Annulla una prenotazione. */
  cancelBooking(id: number): Observable<void> {
    return this.http.delete<void>(`${this.base}/bookings/${id}`);
  }
}