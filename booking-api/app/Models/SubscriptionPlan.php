<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un piano di abbonamento: durata in giorni e numero massimo di corsi seguibili contemporaneamente.
 */
class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'duration_days', 'max_courses', 'is_active'];

    /**
     * Conversioni automatiche dei campi quando si leggono dal database.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'max_courses' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Gli abbonamenti assegnati con questo piano. */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
