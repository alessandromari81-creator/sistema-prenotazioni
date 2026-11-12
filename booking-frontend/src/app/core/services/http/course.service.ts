import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';
import { PaginatedResponse } from '../../interfaces/paginate-response.interface';
import { Course, CourseInput, CourseMaterial, Enrollment } from '../../models/models';

/** Parametri dell'elenco corsi (gli stessi dell'API: ricerca, giorno, ordinamento, paginazione). */
export interface CourseListParams {
  page?: number;
  perPage?: number;
  search?: string;
  dayOfWeek?: number | null;
  sortBy?: 'name' | 'day_of_week' | 'start_time';
  sortDirection?: 'asc' | 'desc';
  includeInactive?: boolean; // vale solo per gli amministratori
}

/**
 * Le chiamate all'API sui corsi: consultazione, gestione (admin), istruttori, iscritti e iscrizioni.
 * Il token di Keycloak lo aggiunge l'interceptor.
 */
@Injectable({ providedIn: 'root' })
export class CourseService {
  private http = inject(HttpClient);
  private base = `${environment.apiUrl}/courses`;

  /** Elenco paginato dei corsi, con ricerca e filtri. */
  list(params: CourseListParams = {}): Observable<PaginatedResponse<Course>> {
    let query = new HttpParams();

    if (params.page) query = query.set('page', params.page);
    if (params.perPage) query = query.set('per_page', params.perPage);
    if (params.search) query = query.set('search', params.search);
    if (params.dayOfWeek) query = query.set('day_of_week', params.dayOfWeek);
    if (params.sortBy) query = query.set('sort_by', params.sortBy);
    if (params.sortDirection) query = query.set('sort_direction', params.sortDirection);
    if (params.includeInactive) query = query.set('all', 1);

    return this.http.get<PaginatedResponse<Course>>(this.base, { params: query });
  }

  /** Un corso con tutti i dati. */
  get(id: number): Observable<Course> {
    return this.http.get<Course>(`${this.base}/${id}`);
  }

  /** Crea un corso (solo admin). */
  create(input: CourseInput): Observable<Course> {
    return this.http.post<Course>(this.base, input);
  }

  /** Modifica un corso (solo admin). */
  update(id: number, input: Partial<CourseInput>): Observable<Course> {
    return this.http.put<Course>(`${this.base}/${id}`, input);
  }

  /** Elimina un corso (solo admin, e solo se non ha iscritti). */
  delete(id: number): Observable<void> {
    return this.http.delete<void>(`${this.base}/${id}`);
  }

  /** Carica o sostituisce l'immagine del corso (solo admin): file JPEG, PNG o WebP fino a 2 MB. Risponde con il corso aggiornato. */
  uploadImage(id: number, file: File): Observable<Course> {
    const body = new FormData();
    body.append('image', file);

    return this.http.post<Course>(`${this.base}/${id}/image`, body);
  }

  /** Toglie l'immagine del corso (solo admin). Risposta 204 senza corpo. */
  removeImage(id: number): Observable<void> {
    return this.http.delete<void>(`${this.base}/${id}/image`);
  }

  /** I materiali PDF del corso (admin, istruttori del corso e iscritti; per gli altri l'API risponde 403). */
  materials(courseId: number): Observable<CourseMaterial[]> {
    return this.http.get<CourseMaterial[]>(`${this.base}/${courseId}/materials`);
  }

  /** Scarica il file PDF di un materiale come Blob. Si passa da HttpClient perché un link normale non manda il token. */
  materialFile(courseId: number, materialId: number): Observable<Blob> {
    return this.http.get(`${this.base}/${courseId}/materials/${materialId}`, { responseType: 'blob' });
  }

  /** Carica un materiale PDF (solo admin): titolo e file in un form multipart. */
  uploadMaterial(courseId: number, title: string, file: File): Observable<CourseMaterial> {
    const body = new FormData();
    body.append('title', title);
    body.append('file', file);

    return this.http.post<CourseMaterial>(`${this.base}/${courseId}/materials`, body);
  }

  /** Elimina un materiale (solo admin). Risposta 204 senza corpo. */
  deleteMaterial(courseId: number, materialId: number): Observable<void> {
    return this.http.delete<void>(`${this.base}/${courseId}/materials/${materialId}`);
  }

  /** Assegna un utente come istruttore del corso (solo admin). Il corso restituito ha l'elenco aggiornato. */
  addInstructor(courseId: number, userId: number): Observable<Course> {
    return this.http.put<Course>(`${this.base}/${courseId}/instructors/${userId}`, {});
  }

  /** Toglie un utente dagli istruttori del corso (solo admin). */
  removeInstructor(courseId: number, userId: number): Observable<Course> {
    return this.http.delete<Course>(`${this.base}/${courseId}/instructors/${userId}`);
  }

  /** Gli studenti iscritti al corso (solo admin e istruttori di quel corso). */
  students(courseId: number): Observable<Enrollment[]> {
    return this.http.get<Enrollment[]>(`${this.base}/${courseId}/students`);
  }

  /** Iscrive l'utente collegato al corso. Il server risponde 204 senza corpo: i dati aggiornati si rileggono con get(). */
  enroll(courseId: number): Observable<void> {
    return this.http.post<void>(`${this.base}/${courseId}/enrollment`, {});
  }

  /** Annulla l'iscrizione dell'utente collegato al corso. Risposta 204 senza corpo: i dati aggiornati si rileggono con get(). */
  cancelEnrollment(courseId: number): Observable<void> {
    return this.http.delete<void>(`${this.base}/${courseId}/enrollment`);
  }

  /** I corsi a cui l'utente collegato è iscritto. */
  myCourses(): Observable<Course[]> {
    return this.http.get<Course[]>(`${environment.apiUrl}/me/courses`);
  }

  /** I corsi che l'utente collegato insegna. */
  teaching(): Observable<Course[]> {
    return this.http.get<Course[]>(`${environment.apiUrl}/me/teaching`);
  }
}
