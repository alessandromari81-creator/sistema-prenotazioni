<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Le regole sugli abbonamenti: assegnarli, annullarli e capire quale vale oggi per un utente.
 */
class SubscriptionService
{
    /**
     * L'abbonamento attivo di $user alla data indicata (oggi se omessa), con il piano già caricato.
     * Restituisce null se l'utente non ne ha uno valido. Gli abbonamenti di una stessa persona non si
     * sovrappongono (lo garantisce assign), quindi ne esiste al massimo uno.
     */
    public function activeFor(User $user, ?Carbon $on = null): ?Subscription
    {
        return $user->subscriptions()->active($on)->with('plan')->first();
    }

    /**
     * Assegna a $user il piano $plan a partire da $startsOn (oggi se omessa). La fine si calcola dalla durata del
     * piano ed è inclusa: un piano di 30 giorni che parte il 1° termina il 30.
     *
     * @throws ValidationException se il piano è disattivato oppure il periodo si sovrappone a un altro abbonamento dell'utente
     */
    public function assign(User $user, SubscriptionPlan $plan, ?Carbon $startsOn = null): Subscription
    {
        if (! $plan->is_active) {
            throw ValidationException::withMessages([
                'subscription_plan_id' => ['Questo piano non è più assegnabile.'],
            ]);
        }

        $start = ($startsOn ?? now())->copy()->startOfDay();
        $end = $start->copy()->addDays($plan->duration_days - 1);

        return DB::transaction(function () use ($user, $plan, $start, $end) {
            // Blocca la riga dell'utente: due assegnazioni contemporanee non possono creare periodi sovrapposti
            User::whereKey($user->id)->lockForUpdate()->first();

            // Due periodi si sovrappongono se uno inizia prima che l'altro finisca e finisce dopo che l'altro è iniziato
            $overlaps = $user->subscriptions()
                ->whereNull('cancelled_at')
                ->whereDate('starts_on', '<=', $end->toDateString())
                ->whereDate('ends_on', '>=', $start->toDateString())
                ->exists();

            if ($overlaps) {
                throw ValidationException::withMessages([
                    'starts_on' => ['L\'utente ha già un abbonamento in questo periodo.'],
                ]);
            }

            return Subscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'starts_on' => $start,
                'ends_on' => $end,
            ])->load('plan');
        });
    }

    /**
     * Annulla un abbonamento (non lo cancella: salva quando è stato annullato). Le iscrizioni già fatte restano valide:
     * l'abbonamento serve per iscriversi, non per continuare a frequentare.
     *
     * @throws ValidationException se è già annullato
     */
    public function cancel(Subscription $subscription): Subscription
    {
        if ($subscription->cancelled_at !== null) {
            throw ValidationException::withMessages([
                'subscription' => ['L\'abbonamento è già annullato.'],
            ]);
        }

        $subscription->update(['cancelled_at' => now()]);

        return $subscription->load('plan');
    }

    /**
     * Il riepilogo dell'abbonamento di $user: quello attivo, quanti corsi sta seguendo e quanti ne può ancora seguire.
     * Senza abbonamento attivo i corsi disponibili sono zero.
     *
     * @return array{subscription: ?Subscription, courses_used: int, courses_left: int}
     */
    public function usage(User $user): array
    {
        $subscription = $this->activeFor($user);

        $used = Enrollment::where('user_id', $user->id)->where('status', Enrollment::ACTIVE)->count();

        return [
            'subscription' => $subscription,
            'courses_used' => $used,
            'courses_left' => $subscription ? max(0, $subscription->plan->max_courses - $used) : 0,
        ];
    }
}
