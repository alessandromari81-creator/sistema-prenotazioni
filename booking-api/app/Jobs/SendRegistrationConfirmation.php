<?php

namespace App\Jobs;

use App\Mail\ConfirmRegistrationMail;
use App\Models\PendingRegistration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendRegistrationConfirmation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Secondi di attesa tra un tentativo e l'altro. */
    public array $backoff = [30, 120];

    public function __construct(public int $pendingRegistrationId) {}

    public function handle(): void
    {
        $pending = PendingRegistration::find($this->pendingRegistrationId);

        if (! $pending || $pending->isExpired()) {
            return;
        }

        // Il token nasce qui, non nel controller: così non passa dalla tabella `jobs`.
        // Salviamo solo l'hash, e sostituire il token invalida le mail precedenti.
        $token = Str::random(64);
        $pending->forceFill(['token_hash' => hash('sha256', $token)])->save();

        $url = rtrim(config('app.frontend_url'), '/').'/confirm-registration?'
            .http_build_query(['email' => $pending->email, 'token' => $token]);

        Mail::to($pending->email)->send(new ConfirmRegistrationMail($pending->name, $url));
    }
}
