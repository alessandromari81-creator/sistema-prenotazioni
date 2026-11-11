<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ConfirmRegistrationMail extends Mailable
{
    public function __construct(
        public string $name,
        public string $confirmUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Conferma la tua registrazione');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.confirm-registration');
    }
}
