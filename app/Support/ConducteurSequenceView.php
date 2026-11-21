<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Conducteur;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * One projection for the initial Chef d'édition page and its live updates.
 * It deliberately exposes the same fields and permissions in both contexts.
 */
final class ConducteurSequenceView
{
    public static function row(Sequence $sequence, Conducteur $conducteur, ?User $user): array
    {
        $notes = trim(strip_tags((string) ($sequence->notes_presentateur ?? '')));

        return [
            'id' => (int) $sequence->id,
            'can_edit' => ! $conducteur->date_locked_at
                && ($user?->can('crudSequencePresentateur', $sequence) ?? false),
            'can_delete' => ! $conducteur->date_locked_at
                && ($user?->can('deleteSequencePresentateur', $sequence) ?? false),
            'code_conducteur' => $sequence->code_conducteur_affichage,
            'code_ligne' => $sequence->code_ligne,
            'conducteur_id' => (int) $sequence->conducteur_id,
            'date_edition' => $conducteur->date_edition?->format('d/m/Y') ?? '',
            'position' => (int) $sequence->position,
            'title' => (string) $sequence->title,
            'type_html' => $sequence->type_badge_html,
            'debut_hm' => $sequence->debut_hm,
            'fin_hm' => $sequence->fin_hm,
            'duree_hm' => $sequence->duree_hm,
            'duration_seconds' => (int) ($sequence->duration_seconds ?? 0),
            'intervenant_nom' => $sequence->intervenant_nom_affichage ?: '—',
            'fonction' => $sequence->intervenant_fonction_affichage
                ?: ($sequence->fonction_text ?: '—'),
            'alerte_info' => (bool) $sequence->alerte_info,
            'has_notes' => $notes !== '',
            'notes_preview' => Str::limit($notes, 140),
        ];
    }
}
