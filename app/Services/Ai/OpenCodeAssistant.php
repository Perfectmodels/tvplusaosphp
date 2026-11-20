<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OpenCodeAssistant
{
    /** @param array<int, array{role: string, content: string}> $history */
    public function answer(string $question, array $history = []): string
    {
        $key = (string) config('services.opencode.key');
        if ($key === '') {
            throw new RuntimeException('L’assistant est temporairement indisponible. Réessayez plus tard.');
        }

        $messages = [
            ['role' => 'system', 'content' => $this->instructions()],
            ...$history,
            ['role' => 'user', 'content' => $question],
        ];

        try {
            $response = Http::withToken($key)->acceptJson()->connectTimeout(5)
                ->timeout(max(5, min(60, (int) config('services.opencode.timeout', 45))))
                ->post(rtrim(config('services.opencode.base_url'), '/').'/chat/completions', [
                    'model' => config('services.opencode.model'),
                    'messages' => $messages,
                    'stream' => false,
                    'temperature' => 0.3,
                    'max_tokens' => 2400,
                ]);
        } catch (ConnectionException) {
            Log::warning('OpenCode assistant connection unavailable');
            throw new RuntimeException('L’assistant ne répond pas pour le moment. Réessayez dans quelques instants.');
        }

        if (! $response->successful()) {
            Log::warning('OpenCode assistant request failed', ['status' => $response->status()]);
            throw new RuntimeException(match ($response->status()) {
                401, 402, 403 => 'L’assistant est indisponible. Veuillez contacter l’administrateur du site pour réactiver le service.',
                429 => 'L’assistant est très sollicité. Réessayez dans quelques instants.',
                default => 'L’assistant est temporairement indisponible. Réessayez plus tard.',
            });
        }

        $answer = $response->json('choices.0.message.content');
        if (! is_string($answer) || trim($answer) === '' || $response->json('choices.0.finish_reason') !== 'stop') {
            throw new RuntimeException('La réponse de l’assistant est incomplète. Réessayez votre question.');
        }

        return mb_substr(trim($answer), 0, 8000);
    }

    private function instructions(): string
    {
        return <<<'PROMPT'
Tu es l’assistant de TV+ Afrique, un outil de coordination éditoriale. Réponds en français, avec des explications claires, courtes et utiles. Adapte-toi à la langue demandée. Tu peux aussi aider à comprendre une notion ou à rédiger un texte.
Tu guides les utilisateurs ; tu ne peux pas exécuter d’actions, modifier des données, envoyer des messages ou consulter la base de données. Ne prétends jamais avoir réalisé une action. N’invente aucun chiffre, nom de salarié, statut de présence, actualité récente ni résultat du site. Pour une question nécessitant des données actuelles, indique la page à consulter et demande les éléments utiles. Les droits d’accès dépendent du rôle de l’utilisateur ; ne propose pas de contourner les autorisations. Ne demande jamais de mot de passe ou de clé API.
Repères vérifiés du fonctionnement de l’application :
- Tableau de bord : vue d’ensemble de la production, planning du jour, suivi des invités, sujets affectés et résultats du pointage des conférences.
- Conducteurs · Rédaction : suivi des sujets, angle, émission, type, journaliste et workflow Terrain → Montage → Habillage → Livraison → Diffusion. Les conducteurs sont rangés par date d’édition, avec navigation entre les dates et pagination. Un conducteur créé pour une autre date ne remplace pas automatiquement l’édition affichée.
- Conducteurs · Chef d’Édition / d’émission : organisation du déroulé de l’émission, également par date d’édition. Le catalogue Émissions regroupe les programmes et permet de retrouver leurs conducteurs.
- Conférence de rédaction : ouvrir les présences depuis le conducteur concerné, marquer le début de la conférence puis indiquer manuellement qui est à l’heure ou en retard en fonction de ce début. Aucun retard n’est déduit automatiquement. Les résultats apparaissent aussi dans le tableau de bord.
- Veille & Sources : collecte et classement automatiques des flux configurés. La vue Actualité gabonaise filtre les sujets liés au Gabon. Les analyses IA présentent résumé, pistes d’angles et points à vérifier avec le lien d’origine ; elles restent à relire et à valider par une personne. Le remix d’article sert à préparer une version éditoriale. Ne confonds pas ces outils avec la présente discussion.
- Journalistes : la création d’un journaliste est reliée à un compte utilisateur et à un matricule d’employé ; les comptes sont gérés selon les autorisations.
- Intervenants / invitations : le bouton de réservation ouvre WhatsApp avec un texte prérempli et le lien du QR code. La personne doit confirmer l’envoi dans WhatsApp.
Réponds en texte simple, avec des étapes numérotées si utile. Ne crée pas de lien ou de nom de bouton si tu ne le connais pas. Si un détail n’est pas couvert par ces repères, signale ton incertitude au lieu d’inventer.
PROMPT;
    }
}
