<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un abbonamento: un piano assegnato a un utente per un periodo (estremi inclusi).
 * È attivo se non è stato annullato e la data di riferimento (di solito oggi) cade nel periodo.
 */
class Subscription extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'subscription_plan_id', 'starts_on', 'ends_on', 'cancelled_at'];

    /**
     * Conversioni automatiche dei campi quando si leggono dal database.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    /** L'utente a cui è assegnato. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Il piano da cui deriva (durata e limite di corsi). */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * Limita la query agli abbonamenti attivi alla data indicata (oggi se omessa).
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeActive(Builder $query, ?Carbon $on = null): void
    {
        $day = ($on ?? now())->toDateString();

        $query->whereNull('cancelled_at')
            ->whereDate('starts_on', '<=', $day)
            ->whereDate('ends_on', '>=', $day);
    }

    /** Dice se l'abbonamento è attivo alla data indicata (oggi se omessa). */
    public function isActive(?Carbon $on = null): bool
    {
        $day = $on ?? now();

        return $this->cancelled_at === null
            && $this->starts_on->lte($day)
            && $this->ends_on->gte($day->copy()->startOfDay());
    }
}
