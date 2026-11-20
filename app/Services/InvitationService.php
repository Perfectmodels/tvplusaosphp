<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Intervenant;
use App\Models\ParcoursIntervenant;
use App\Models\User;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Invitation publique d'un intervenant : jeton permanent, QR, message et
 * lien WhatsApp, confirmation depuis le téléphone (sans compte).
 */
class InvitationService
{
    public const MEDIUM_WHATSAPP = 'whatsapp';

    public const MEDIUM_QR = 'whatsapp_qr';

    public const JOURS_VALIDITE = 7;

    public function __construct(
        private readonly ParcoursIntervenantService $parcoursService,
    ) {}

    /** URL publique stable encodée dans le QR. */
    public function urlIntervenant(Intervenant $intervenant): string
    {
        return $intervenant->urlInvitation();
    }

    /** Image PNG du QR (générée à la volée, jamais stockée). */
    public function qrPng(Intervenant $intervenant, int $size = 512): string
    {
        $contenu = $intervenant->urlQrAcces();

        try {
            return (string) QrCode::format('png')
                ->size($size)
                ->errorCorrection('H')
                ->margin(2)
                ->generate($contenu);
        } catch (\Throwable) {
            // Repli sans Imagick (fréquent sur mutualisé) : rendu GD direct.
            return $this->qrPngAvecGd($contenu, $size, 2);
        }
    }

    /** Image PNG du QR d'accès, liée à une invitation précise. */
    public function qrPngPourParcours(ParcoursIntervenant $parcours, int $size = 512): string
    {
        $token = (string) $parcours->qr_token;

        try {
            return (string) QrCode::format('png')
                ->size($size)
                ->errorCorrection('H')
                ->margin(2)
                ->generate($token);
        } catch (\Throwable) {
            return $this->qrPngAvecGd($token, $size, 2);
        }
    }

    /** Version SVG (impression / affichage net). */
    public function qrSvg(Intervenant $intervenant, int $size = 512): string
    {
        return (string) QrCode::format('svg')
            ->size($size)
            ->errorCorrection('H')
            ->margin(2)
            ->generate($intervenant->urlQrAcces());
    }

    /**
     * Message WhatsApp pré-rempli (invitation personnelle).
     *
     * Mise en forme WhatsApp : *gras*, _italique_. Le texte reste lisible
     * tel quel si l'opérateur le copie dans un e-mail.
     */
    public function message(Intervenant $intervenant, ?ParcoursIntervenant $parcours = null): string
    {
        $prenom = trim((string) ($intervenant->prenom ?? '')) ?: 'Madame, Monsieur';
        $emission = $this->nomEmission($parcours);
        $passage = $parcours?->heure_passage_prevue;

        $details = [];
        if ($emission) {
            $details[] = '📺 *Émission :* '.$emission;
        }

        if ($passage) {
            $details[] = '📅 *Date :* '.$this->dateLongue($passage);
            $details[] = '🕒 *Heure :* '.$passage->format('H\hi').' (heure de Libreville)';
        }

        $lignes = [
            '*TV+ Afrique | Votre invitation à notre émission*',
            '',
            "Bonjour {$prenom},",
            '',
            'C’est avec grand plaisir que nous vous accueillerons sur le plateau de *TV+ Afrique* à l’occasion de notre émission'
                .($emission ? ' *'.$emission.'*.' : '.'),
            '',
            'Nous sommes ravis de vous compter parmi nos invités et avons hâte de partager ce moment avec vous !',
            '',
        ];

        if ($details !== []) {
            $lignes[] = '*Voici les détails de votre passage :*';
            $lignes[] = '';
            array_push($lignes, ...$details);
            $lignes[] = '';
        }

        array_push(
            $lignes,
            '*Votre invitation personnelle :*',
            $intervenant->urlInvitation(),
            '',
            '*Votre QR code d’accès :*',
            $parcours?->urlQr() ?? route('invitations.qr', ['token' => $intervenant->tokenInvitation()]),
            '',
            'À votre arrivée, notre équipe de production sera heureuse de vous accueillir. '
                .'Il vous suffira de présenter votre QR code pour faciliter votre prise en charge.',
            '',
            'Nous vous remercions pour votre disponibilité et nous réjouissons de vous retrouver très prochainement.',
            '',
            '*À très bientôt sur TV+ Afrique !*',
            '',
            '_L’équipe de production_',
            '*TV+ Afrique*',
        );

        return implode("\n", $lignes);
    }

    /** Date longue en français : « Samedi 14 novembre 2026 ». */
    private function dateLongue(CarbonInterface $date): string
    {
        return ucfirst($date->copy()->locale('fr')->translatedFormat('l j F Y'));
    }

    /** Lien wa.me pré-rempli, ou null si aucun numéro exploitable. */
    public function lienWhatsApp(Intervenant $intervenant, ?ParcoursIntervenant $parcours = null): ?string
    {
        $numero = $this->numeroWhatsApp($intervenant->telephone_1 ?: $intervenant->phone);
        if (! $numero) {
            return null;
        }

        return 'https://wa.me/'.$numero.'?text='.rawurlencode($this->message($intervenant, $parcours));
    }

    /**
     * Normalise un numéro gabonais au format international attendu par wa.me
     * (indicatif 241, sans « + », sans espaces).
     */
    public function numeroWhatsApp(?string $telephone): ?string
    {
        if (! $telephone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $telephone) ?? '';
        if ($digits === '') {
            return null;
        }

        // +241 06 12 34 56 → 24106123456 (11 chiffres)
        if (str_starts_with($digits, '241')) {
            return strlen($digits) >= 10 ? $digits : null;
        }

        // Numéro local à 9 chiffres : 061234567 → 24161234567
        if (strlen($digits) === 9 && str_starts_with($digits, '0')) {
            return '241'.substr($digits, 1);
        }

        // Numéro national déjà sans le zéro : 61234567 → 24161234567
        if (strlen($digits) === 8) {
            return '241'.$digits;
        }

        return strlen($digits) >= 10 ? $digits : null;
    }

    /** Marque l'envoi WhatsApp et ouvre la fenêtre de confirmation de 7 jours. */
    public function marquerEnvoi(ParcoursIntervenant $parcours): void
    {
        $this->parcoursService->marquerQrEnvoye($parcours, self::MEDIUM_WHATSAPP, self::JOURS_VALIDITE);
    }

    /**
     * Confirme une invitation depuis le lien public (idempotent).
     *
     * @return array{ok: bool, message: string, deja_fait?: bool, place_dans_conducteur?: bool}
     */
    public function confirmer(
        ParcoursIntervenant $parcours,
        ?Request $request = null,
        ?User $by = null,
    ): array {
        if ($parcours->estConfirmee()) {
            return [
                'ok' => true,
                'deja_fait' => true,
                'place_dans_conducteur' => false,
                'message' => 'Votre participation est déjà confirmée. Merci !',
            ];
        }

        if ($parcours->estExpiree()) {
            return [
                'ok' => false,
                'message' => 'Le délai de confirmation de 7 jours est dépassé. Contactez la production pour régulariser votre invitation.',
            ];
        }

        if (! $parcours->estConfirmable()) {
            return [
                'ok' => false,
                'message' => 'Cette invitation ne peut plus être confirmée. Contactez la production.',
            ];
        }

        // Trace de la confirmation publique (IP + navigateur).
        $metadata = (array) ($parcours->metadata ?? []);
        $metadata['confirmation_publique'] = [
            'at' => now()->toIso8601String(),
            'ip' => $request?->ip(),
            'user_agent' => mb_substr((string) $request?->userAgent(), 0, 255),
        ];
        $parcours->metadata = $metadata;
        $parcours->save();

        $resultat = $this->parcoursService->confirmer(
            $parcours,
            $by,
            self::MEDIUM_QR,
            'Confirmation depuis le lien WhatsApp / QR public',
        );

        return [
            'ok' => true,
            'deja_fait' => (bool) ($resultat['resultat']['deja_fait'] ?? false),
            'place_dans_conducteur' => (bool) $resultat['place_dans_conducteur'],
            'message' => 'Votre participation est confirmée. Merci !',
        ];
    }

    /**
     * Rendu PNG du QR sans Imagick, via l'extension GD.
     * Utilisé en repli sur les hébergements mutualisés qui n'ont que GD.
     */
    private function qrPngAvecGd(string $contenu, int $size, int $margin): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new \RuntimeException('Impossible de générer le QR : extensions Imagick et GD absentes.');
        }

        $matrice = Encoder::encode($contenu, ErrorCorrectionLevel::forBits(0x02))->getMatrix();
        $modules = $matrice->getWidth();

        $total = $modules + ($margin * 2);
        $echelle = max(1, intdiv($size, $total));
        $pixels = $total * $echelle;
        $decalage = $margin * $echelle;

        $image = imagecreatetruecolor($pixels, $pixels);
        $blanc = imagecolorallocate($image, 255, 255, 255);
        $noir = imagecolorallocate($image, 0, 0, 0);

        imagefilledrectangle($image, 0, 0, $pixels - 1, $pixels - 1, $blanc);

        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrice->get($x, $y) !== 1) {
                    continue;
                }

                imagefilledrectangle(
                    $image,
                    $decalage + ($x * $echelle),
                    $decalage + ($y * $echelle),
                    $decalage + (($x + 1) * $echelle) - 1,
                    $decalage + (($y + 1) * $echelle) - 1,
                    $noir,
                );
            }
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    /**
     * Confirmation déclenchée par le SCAN du QR (utilisateur connecté).
     *
     * C'est l'opérateur qui valide : il confirme l'invitation la plus proche
     * encore en attente, avec la même règle métier que le bouton « Accepté ».
     *
     * @return array{ok: bool, message: string, deja_fait?: bool, place_dans_conducteur?: bool, parcours?: ParcoursIntervenant|null}
     */
    public function confirmerParScan(
        Intervenant $intervenant,
        ?User $by = null,
        ?Request $request = null,
    ): array {
        $parcours = $intervenant->derniereInvitationEnAttente();

        if (! $parcours) {
            // Idempotence : une invitation déjà confirmée récemment ?
            $confirme = $intervenant->parcoursIntervenants()
                ->whereNotNull('confirme_at')
                ->orderByDesc('confirme_at')
                ->first();

            return [
                'ok' => (bool) $confirme,
                'deja_fait' => (bool) $confirme,
                'parcours' => $confirme,
                'message' => $confirme
                    ? 'Invitation déjà confirmée.'
                    : 'Aucune invitation en attente pour cet intervenant.',
            ];
        }

        $resultat = $this->confirmer($parcours, $request, $by);

        return array_merge($resultat, ['parcours' => $parcours]);
    }

    private function nomEmission(?ParcoursIntervenant $parcours): ?string
    {
        if (! $parcours) {
            return null;
        }

        $nom = $parcours->emission?->nom
            ?? $parcours->sequence?->emission?->nom
            ?? $parcours->conducteur?->emission?->nom;

        return filled($nom) ? (string) $nom : null;
    }
}
