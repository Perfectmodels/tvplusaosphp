<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envoi des coordonnées d'accès (e-mail + mot de passe temporaire) d'un compte.
 *
 * Le mot de passe temporaire doit obligatoirement être changé à la première
 * connexion (flag password_changed_at = null).
 */
class CoordonneesCompteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $nom,
        public readonly string $email,
        public readonly string $motDePasseTemporaire,
        public readonly string $contexte = 'création',
    ) {}

    public function envelope(): Envelope
    {
        $objet = match ($this->contexte) {
            'réinitialisation' => 'Votre nouveau mot de passe temporaire',
            'inscription approuvée' => 'Votre inscription a été approuvée — accès à votre compte',
            default => 'Vos identifiants d\'accès',
        };

        return new Envelope(subject: $objet);
    }

    public function content(): Content
    {
        $vue = match ($this->contexte) {
            'réinitialisation' => 'mail.coordonnees-compte-reinitialisation',
            'inscription approuvée' => 'mail.coordonnees-compte-inscription-approuvee',
            default => 'mail.coordonnees-compte-creation',
        };
        $data = [
            'nom' => $this->nom,
            'email' => $this->email,
            'motDePasseTemporaire' => $this->motDePasseTemporaire,
        ];

        return new Content(htmlString: view($vue, $data)->render());
    }

    /**
     * Envoi sécurisé : une erreur SMTP n'interrompt jamais la création ou la
     * réinitialisation du compte (l'erreur est journalisée et l'admin garde
     * les coordonnées affichées à l'écran pour communication manuelle).
     */
    public static function envoyer(User $user, string $motDePasseTemporaire, string $contexte = 'création'): bool
    {
        try {
            Mail::to($user->email)->send(new self(
                (string) $user->nomComplet,
                (string) $user->email,
                $motDePasseTemporaire,
                $contexte
            ));

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
