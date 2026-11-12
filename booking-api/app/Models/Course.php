<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un corso della palestra: si tiene ogni settimana nello stesso giorno e alla stessa ora, ha un numero massimo
 * di posti, uno o più istruttori e degli iscritti.
 */
class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'image_path',
        'capacity',
        'day_of_week',
        'start_time',
        'duration_minutes',
        'bookable_id',
        'is_active',
    ];

    /**
     * Conversioni automatiche dei campi quando si leggono dal database.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'day_of_week' => 'integer',
            'duration_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Gli istruttori del corso (utenti assegnati dall'amministratore). */
    public function instructors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_instructor')->withTimestamps();
    }

    /** Tutte le iscrizioni al corso, anche quelle annullate. */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** Le iscrizioni valide: quelle che occupano un posto. */
    public function activeEnrollments(): HasMany
    {
        return $this->enrollments()->where('status', Enrollment::ACTIVE);
    }

    /** I materiali PDF del corso. */
    public function materials(): HasMany
    {
        return $this->hasMany(CourseMaterial::class);
    }

    /** La sala in cui si tiene il corso, se indicata. */
    public function bookable(): BelongsTo
    {
        return $this->belongsTo(Bookable::class);
    }

    /** Dice se $user è tra gli istruttori del corso. */
    public function isTaughtBy(User $user): bool
    {
        return $this->instructors()->whereKey($user->getKey())->exists();
    }
}
