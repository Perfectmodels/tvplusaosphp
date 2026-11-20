<?php

namespace App\Mail;

use App\Models\WorkspaceDossier;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationDossierMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $titre,
        public readonly string $message,
        public readonly WorkspaceDossier $dossier,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'TV+A OS — Notification dossier : '.$this->titre);
    }

    public function content(): Content
    {
        return new Content(htmlString: view('mail.notification-dossier', [
            'titre' => $this->titre,
            'message' => $this->message,
            'dossier' => $this->dossier,
        ])->render());
    }
}
