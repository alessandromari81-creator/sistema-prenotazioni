<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\MassPrunable;

/**
 * Registrazione in attesa di conferma via email. Contiene i dati del form (password
 * già hashata) e l'hash del token inviato per mail; l'utente vero viene creato solo
 * quando il link viene confermato. le righe scadute
 * vengono eliminate in automatico da `model:prune` (vedi routes/console.php).
 */
class PendingRegistration extends Model
{
    use MassPrunable;

    /** Validità del link di conferma, in ore. */
    public const TTL_HOURS = 24;

    protected $fillable = ['email', 'name', 'password', 'address', 'token_hash', 'expires_at'];

    protected $hidden = ['password', 'token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function prunable(): Builder
    {
        return static::where('expires_at', '<', now());
    }
}
