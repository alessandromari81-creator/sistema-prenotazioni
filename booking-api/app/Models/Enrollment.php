<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * L'iscrizione di un utente a un corso. Stato "active" = occupa un posto; "cancelled" = annullata (resta nello storico).
 */
class Enrollment extends Model
{
    use HasFactory;

    /** Iscrizione valida. */
    public const ACTIVE = 'active';

    /** Iscrizione annullata dall'utente. */
    public const CANCELLED = 'cancelled';

    protected $fillable = ['course_id', 'user_id', 'subscription_id', 'status'];

    /** Il corso a cui ci si è iscritti. */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** L'utente iscritto. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** L'abbonamento usato per iscriversi. */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
