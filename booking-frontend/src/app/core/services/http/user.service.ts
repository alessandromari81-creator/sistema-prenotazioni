import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';
import { PaginatedResponse } from '../../interfaces/paginate-response.interface';
import { User } from '../../models/models';

/**
 * Le chiamate all'API sugli utenti. L'elenco è riservato agli amministratori: serve a scegliere
 * chi assegnare come istruttore o a chi dare un abbonamento.
 */
@Injectable({ providedIn: 'root' })
export class UserService {
  private http = inject(HttpClient);

  /** Elenco paginato degli utenti, con ricerca per nome o email. */
  list(params: { search?: string; page?: number; perPage?: number } = {}): Observable<PaginatedResponse<User>> {
    let query = new HttpParams();

    if (params.search) query = query.set('search', params.search);
    if (params.page) query = query.set('page', params.page);
    if (params.perPage) query = query.set('per_page', params.perPage);

    return this.http.get<PaginatedResponse<User>>(`${environment.apiUrl}/users`, { params: query });
  }
}
