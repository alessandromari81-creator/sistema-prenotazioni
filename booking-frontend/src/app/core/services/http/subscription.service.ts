import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';
import {
  Subscription,
  SubscriptionPlan,
  SubscriptionPlanInput,
  SubscriptionUsage,
} from '../../models/models';

/**
 * Le chiamate all'API su piani di abbonamento e abbonamenti.
 * La gestione (creare piani, assegnare e annullare abbonamenti) è riservata agli amministratori.
 */
@Injectable({ providedIn: 'root' })
export class SubscriptionService {
  private http = inject(HttpClient);
  private api = environment.apiUrl;

  /** I piani, dal più breve al più lungo. Gli amministratori possono chiedere anche quelli disattivati. */
  plans(includeInactive = false): Observable<SubscriptionPlan[]> {
    let params = new HttpParams();
    if (includeInactive) params = params.set('all', 1);

    return this.http.get<SubscriptionPlan[]>(`${this.api}/subscription-plans`, { params });
  }

  /** Crea un piano (solo admin). */
  createPlan(input: SubscriptionPlanInput): Observable<SubscriptionPlan> {
    return this.http.post<SubscriptionPlan>(`${this.api}/subscription-plans`, input);
  }

  /** Modifica un piano (solo admin). Gli abbonamenti già assegnati non cambiano. */
  updatePlan(id: number, input: Partial<SubscriptionPlanInput>): Observable<SubscriptionPlan> {
    return this.http.put<SubscriptionPlan>(`${this.api}/subscription-plans/${id}`, input);
  }

  /** Elimina un piano (solo admin, e solo se non è mai stato assegnato). */
  deletePlan(id: number): Observable<void> {
    return this.http.delete<void>(`${this.api}/subscription-plans/${id}`);
  }

  /** Il riepilogo dell'abbonamento dell'utente collegato: quello attivo e quanti corsi può ancora seguire. */
  mine(): Observable<SubscriptionUsage> {
    return this.http.get<SubscriptionUsage>(`${this.api}/me/subscription`);
  }

  /** Gli abbonamenti di un utente, dal più recente (solo admin). */
  ofUser(userId: number): Observable<Subscription[]> {
    return this.http.get<Subscription[]>(`${this.api}/users/${userId}/subscriptions`);
  }

  /** Assegna un piano a un utente (solo admin). Senza data di inizio parte da oggi. */
  assign(userId: number, planId: number, startsOn?: string): Observable<Subscription> {
    return this.http.post<Subscription>(`${this.api}/users/${userId}/subscriptions`, {
      subscription_plan_id: planId,
      ...(startsOn ? { starts_on: startsOn } : {}),
    });
  }

  /** Annulla un abbonamento (solo admin). Le iscrizioni già fatte restano valide. */
  cancel(subscriptionId: number): Observable<Subscription> {
    return this.http.delete<Subscription>(`${this.api}/subscriptions/${subscriptionId}`);
  }
}
