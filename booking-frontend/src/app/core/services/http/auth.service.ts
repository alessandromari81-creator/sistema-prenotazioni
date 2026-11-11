import { Injectable, computed, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { Observable, catchError, firstValueFrom, of, switchMap, tap } from 'rxjs';
import { environment } from '../../../../environments/environment';
import { RegisterInput, User } from '../../models/models';


@Injectable({ providedIn: 'root' })
export class AuthService {
  private http = inject(HttpClient);
  private router = inject(Router);

  // L'unico stato è l'utente: il cookie di sessione lo gestisce il browser, non noi
  readonly user = signal<User | null>(null);
  readonly isLoggedIn = computed(() => this.user() !== null);
  readonly isAdmin = computed(() => this.user()?.role === 'admin');

  /** Eseguita all'avvio dell'app: chiede al server "sono già loggato?" (es. dopo un refresh). */
  init(): Promise<void> {
    return firstValueFrom(
      this.http.get<User>(`${environment.apiUrl}/user`).pipe(
        tap((u) => this.user.set(u)),
        catchError(() => of(null)) // 401 = non loggato: non è un errore, si resta anonimi
      )
    ).then(() => undefined);
  }

  /** Login: 1) ottiene il cookie CSRF, 2) invia le credenziali, 3) salva l'utente. */
  login(email: string, password: string): Observable<User> {
    return this.http.get(environment.csrfUrl).pipe(
      switchMap(() =>
        this.http.post<User>(`${environment.apiUrl}/login`, { email, password })
      ),
      tap((u) => this.user.set(u))
    );
  }

  /**
   * Registrazione: il server salva la richiesta e invia una mail con il link di conferma.
   * L'utente NON è ancora creato né loggato.
   */
  register(data: RegisterInput): Observable<{ message: string }> {
    return this.http.get(environment.csrfUrl).pipe(
      switchMap(() =>
        this.http.post<{ message: string }>(`${environment.apiUrl}/register`, data)
      )
    );
  }

  /** Conferma la registrazione con email e token ricevuti nel link della mail. */
  confirmRegistration(email: string, token: string): Observable<{ message: string }> {
    return this.http.get(environment.csrfUrl).pipe(
      switchMap(() =>
        this.http.post<{ message: string }>(`${environment.apiUrl}/register/confirm`, { email, token })
      )
    );
  }

  /** Logout: avvisa il server (che distrugge la sessione), poi pulisce lo stato locale. */
  logout(): void {
    this.http.post(`${environment.apiUrl}/logout`, {}).subscribe({
      complete: () => this.clear(),
      error: () => this.clear(),
    });
  }

  /** Azzera l'utente e rimanda al login. */
  clear(): void {
    this.user.set(null);
    this.router.navigate(['/login']);
  }
}