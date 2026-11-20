<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Journal de sécurité (canal `security` → storage/logs/security.log).
 *
 * Trace les événements sensibles : connexions, échecs, verrouillages de compte,
 * déconnexions et changements de mot de passe — avec IP et User-Agent pour
 * permettre une investigation en cas d'incident.
 */
class SecurityLogger
{
    public const CONNEXION_REUSSIE  = 'connexion.reussie';
    public const CONNEXION_ECHOUEE  = 'connexion.echouee';
    public const COMPTE_VERROUILLE  = 'compte.verrouille';
    public const DECONNEXION        = 'deconnexion';
    public const MOT_DE_PASSE_CHANG = 'mot_de_passe.change';

    public function journaliser(string $evenement, ?string $email = null, array $context = []): void
    {
        try {
            $request = request();

            Log::channel('security')->info($evenement, array_merge([
                'email'  => $email,
                'ip'     => $request?->ip(),
                'agent'  => mb_substr((string) $request?->userAgent(), 0, 255),
                'url'    => $request?->fullUrl(),
                'methode'=> $request?->method(),
                'date'   => now()->toIso8601String(),
            ], $context));
        } catch (\Throwable) {
            // La journalisation ne doit jamais faire échouer une requête.
        }
    }

    public function connexionReussie(?string $email, int|string|null $userId = null): void
    {
        $this->journaliser(self::CONNEXION_REUSSIE, $email, ['user_id' => $userId]);
    }

    public function connexionEchouee(?string $email, string $raison = 'identifiants invalides'): void
    {
        $this->journaliser(self::CONNEXION_ECHOUEE, $email, ['raison' => $raison]);
    }

    public function compteVerrouille(?string $email, int $secondes): void
    {
        $this->journaliser(self::COMPTE_VERROUILLE, $email, ['duree_secondes' => $secondes]);
    }

    public function deconnexion(?string $email, int|string|null $userId = null): void
    {
        $this->journaliser(self::DECONNEXION, $email, ['user_id' => $userId]);
    }

    public function motDePasseChange(?string $email, int|string|null $userId = null): void
    {
        $this->journaliser(self::MOT_DE_PASSE_CHANG, $email, ['user_id' => $userId]);
    }

    /**
     * Raccourci statique : SecurityLogger::log('connexion.reussie', 'a@b.test').
     */
    public static function log(string $evenement, ?string $email = null, array $context = []): void
    {
        app(self::class)->journaliser($evenement, $email, $context);
    }
}
