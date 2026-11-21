<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatutConducteurEnum;
use App\Models\Conducteur;
use App\Models\Sequence;
use App\Services\Pdf\TvAosPdfDocument;
use InvalidArgumentException;

/**
 * Conducteur technique (A4 paysage), script présentateur (A4 portrait) ou
 * dossier complet (paysage puis portrait).
 *
 * Des pages vectorielles, texte Unicode sélectionnable, polices Pilat
 * intégrées, aucun binaire externe ou nouvelle dépendance Composer.
 */
final class ConducteurPdfExporter
{
    public const TECHNIQUE = 'technique';
    public const SCRIPT = 'script';
    public const COMPLET = 'complet';

    private const NAVY = '#001A41';
    private const BLUE = '#0054CD';
    private const LIGHT_BLUE = '#EAF2FF';
    private const LIGHT = '#F3F6FA';
    private const TEXT = '#181C1E';
    private const MUTED = '#576272';
    private const BORDER = '#DFE6EF';
    private const RED = '#BA1A1A';
    private const WHITE = '#FFFFFF';

    private const A4_W = 595.28;
    private const A4_H = 841.89;
    private const MARGIN = 40.0;

    public function export(Conducteur $conducteur, string $variant = self::COMPLET): string
    {
        if (! in_array($variant, [self::TECHNIQUE, self::SCRIPT, self::COMPLET], true)) {
            throw new InvalidArgumentException('Format PDF inconnu.');
        }

        $conducteur->loadMissing(['emission', 'presentateur']);
        $sequences = $conducteur->sequences()
            ->with('intervenants')
            ->orderBy('position')
            ->get();

        $pdf = new TvAosPdfDocument();
        $draft = in_array($conducteur->status, [
            StatutConducteurEnum::Brouillon,
            StatutConducteurEnum::En_construction,
            StatutConducteurEnum::A_valider,
        ], true);

        if (in_array($variant, [self::TECHNIQUE, self::COMPLET], true)) {
            $this->technical($pdf, $conducteur, $sequences, $draft);
        }

        if (in_array($variant, [self::SCRIPT, self::COMPLET], true)) {
            $this->script($pdf, $conducteur, $sequences, $draft);
        }

        $total = $pdf->pageCount();
        for ($page = 0; $page < $total; $page++) {
            $pdf->usePage($page);
            [$width, $height] = $pdf->pageSize($page);
            $pdf->line(self::MARGIN, $height - 44, $width - self::MARGIN, $height - 44, self::BORDER);
            $this->brand($pdf, 42, $height - 34, 11, self::NAVY);
            $pdf->text('DOCUMENT INTERNE  |  '.($conducteur->code_conducteur ?? 'TV+A OS'), 127, $height - 33, 8.5, self::MUTED);
            $pdf->textRight('Page '.($page + 1).' / '.$total, $width - 42, $height - 33, 9, self::MUTED, 'bold');
        }

        $title = ($variant === self::TECHNIQUE ? 'Conducteur technique' : ($variant === self::SCRIPT ? 'Script présentateur' : 'Dossier complet'))
            .' - '.$this->emissionName($conducteur);

        return $pdf->save($title);
    }

    private function technical(TvAosPdfDocument $pdf, Conducteur $c, iterable $sequences, bool $draft): void
    {
        $width = self::A4_H;
        $height = self::A4_W;
        $y = $this->technicalPage($pdf, $c, $draft, true);
        $number = 0;

        foreach ($sequences as $sequence) {
            $number++;
            $titleLines = $pdf->wrap((string) $sequence->title, 183, 11, 'bold');
            $guestLines = $pdf->wrap($this->guests($sequence), 129, 9.6);
            $fullNote = $this->plainText((string) ($sequence->note_regie ?? ''));
            $noteLines = $pdf->wrap($fullNote === '' ? '—' : $fullNote, 171, 9.6);
            $notePreview = array_slice($noteLines, 0, 3);
            if (count($noteLines) > 3) {
                $notePreview[] = 'Suite en annexe…';
            }
            $rowHeight = max(50, 12 * max(
                count($titleLines),
                count($guestLines),
                count($notePreview),
                2
            ) + 15);

            if ($y + $rowHeight > $height - 63) {
                $y = $this->technicalPage($pdf, $c, $draft);
            }

            $pdf->rect(40, $y, $width - 80, $rowHeight, $number % 2 === 0 ? self::LIGHT : self::WHITE);
            $pdf->line(40, $y + $rowHeight, $width - 40, $y + $rowHeight, self::BORDER, 0.5);
            $pdf->text(str_pad((string) $sequence->position, 2, '0', STR_PAD_LEFT), 48, $y + 9, 11, self::BLUE, 'bold');
            $pdf->text((string) ($sequence->debut_hm ?: '—'), 48, $y + 25, 10, self::TEXT, 'bold');
            $this->drawLines($pdf, $titleLines, 121, $y + 10, 11.3, 183, self::TEXT, 'bold', 12.5);
            $this->drawLines($pdf, $pdf->wrap($sequence->type?->label() ?? '—', 70, 9.8), 317, $y + 12, 9.8, 70, self::MUTED);
            $pdf->text((string) ($sequence->duree_hm ?: '00:00'), 400, $y + 12, 10, self::TEXT, 'bold');
            $this->drawLines($pdf, $guestLines, 470, $y + 11, 9.6, 129, self::TEXT);
            $this->drawLines($pdf, $notePreview, 611, $y + 10, 9.6, 171, self::TEXT);
            $y += $rowHeight;
        }

        if ($number === 0) {
            $pdf->rect(40, $y + 7, $width - 80, 66, self::LIGHT);
            $pdf->text('Aucune séquence programmée pour cette édition.', 57, $y + 30, 13, self::MUTED);
            $y += 73;
        }

        $details = [];
        foreach ($sequences as $sequence) {
            $notes = $this->plainText((string) ($sequence->note_regie ?? ''));
            $angle = $this->plainText((string) ($sequence->angle_editorial ?? ''));
            $summary = $this->plainText((string) ($sequence->summary ?? ''));
            if (mb_strlen($notes) > 85 || $angle !== '' || $summary !== '') {
                $details[] = ['sequence' => $sequence, 'notes' => $notes, 'angle' => $angle, 'summary' => $summary];
            }
        }

        if ($details !== []) {
            $y += 28;
            if ($y > $height - 122) {
                $y = $this->technicalPage($pdf, $c, $draft, false, false);
            }
            $pdf->text('NOTES DE PRODUCTION ET CONSIGNES DÉTAILLÉES', 42, $y, 15, self::NAVY, 'bold');
            $y += 29;

            foreach ($details as $detail) {
                $sequence = $detail['sequence'];
                $titleLines = $pdf->wrap(str_pad((string) $sequence->position, 2, '0', STR_PAD_LEFT)
                    .'  '.$sequence->title, $width - 88, 12, 'bold');
                if ($y + 68 > $height - 64) {
                    $y = $this->technicalPage($pdf, $c, $draft, false, false);
                }
                $pdf->rect(40, $y - 4, $width - 80, 4, self::BLUE);
                $this->drawLines($pdf, $titleLines, 44, $y + 7, 12, $width - 88, self::NAVY, 'bold', 14);
                $y += count($titleLines) * 14 + 20;

                foreach (['Consignes régie' => $detail['notes'], 'Angle éditorial' => $detail['angle'], 'Résumé' => $detail['summary']] as $label => $value) {
                    if ($value === '') {
                        continue;
                    }
                    $y = $this->pagedParagraph($pdf, $value, $label, $y, $width - 90, 10.5, 14,
                        fn (): float => $this->technicalPage($pdf, $c, $draft, false, false),
                        $height - 65);
                    $y += 9;
                }
                $y += 13;
            }
        }
    }

    private function technicalPage(
        TvAosPdfDocument $pdf,
        Conducteur $c,
        bool $draft,
        bool $first = false,
        bool $withTable = true,
    ): float {
        $pdf->newPage(self::A4_H, self::A4_W);
        $h = self::A4_W;
        $pdf->rect(0, 0, self::A4_H, $first ? 111 : 80, self::NAVY);
        $this->brand($pdf, 40, 19, 22);
        $pdf->textRight('CONDUCTEUR TECHNIQUE  /  RÉGIE', self::A4_H - 40, 22, 11, '#DAE7FF', 'bold');
        $pdf->text($this->oneLine($this->emissionName($c), 55), 40, $first ? 54 : 50,
            $first ? 24 : 14, self::WHITE, 'bold');

        if ($draft) {
            $pdf->text('BROUILLON', 294, 346, 55, '#ECF0F5', 'bold', 35);
        }

        if ($first) {
            $stats = [
                ['DÉBUT', $this->startTime($c)],
                ['DURÉE PROGRAMMÉE', (string) ($c->total_duration_hm ?: '00:00')],
                ['OBJECTIF', $c->target_duration_sec ? $this->clock((int) $c->target_duration_sec) : '—'],
                ['SÉQUENCES', (string) $c->sequences()->count()],
                ['STATUT', $c->status?->label() ?? '—'],
            ];
            $cardWidth = 145;
            foreach ($stats as $i => [$label, $value]) {
                $x = 40 + $i * ($cardWidth + 8);
                $pdf->rect($x, 121, $cardWidth, 52, self::LIGHT);
                $pdf->rect($x, 121, 3, 52, self::BLUE);
                $pdf->text($label, $x + 11, 128, 8.5, self::MUTED, 'bold');
                $pdf->text($this->oneLine($value, 24), $x + 11, 143, 15, self::NAVY, 'bold');
            }
            $pdf->text('ÉDITION : '.($c->date_edition?->format('d/m/Y') ?? '—')
                .'    |    PRÉSENTATEUR : '.$this->presenter($c), 41, 181, 9.7, self::MUTED);
            $y = 207;
        } else {
            $pdf->text('Édition : '.($c->date_edition?->format('d/m/Y') ?? '—').'    |    Suite', 41, 91, 9.5, self::MUTED);
            $y = 117;
        }

        if ($withTable) {
            $pdf->rect(40, $y, self::A4_H - 80, 29, self::NAVY);
            foreach ([[48, '# / HEURE'], [121, 'SÉQUENCE'], [317, 'TYPE'], [400, 'DURÉE'],
                [470, 'INVITÉS'], [611, 'CONSIGNES RÉGIE']] as [$x, $label]) {
                $pdf->text($label, $x, $y + 8, 9.5, self::WHITE, 'bold');
            }
            return $y + 29;
        }

        return $y;
    }

    private function script(TvAosPdfDocument $pdf, Conducteur $c, iterable $sequences, bool $draft): void
    {
        $y = $this->scriptPage($pdf, $c, $draft, true);
        $count = 0;

        foreach ($sequences as $sequence) {
            $count++;
            if ($y > self::A4_H - 160) {
                $y = $this->scriptPage($pdf, $c, $draft);
            }

            $titleLines = $pdf->wrap((string) $sequence->title, 354, 15, 'bold');
            $headerHeight = max(55, 23 + count($titleLines) * 18);
            if ($y + $headerHeight + 42 > self::A4_H - 64) {
                $y = $this->scriptPage($pdf, $c, $draft);
            }

            $pdf->rect(40, $y, 34, 34, self::BLUE);
            $pdf->text(str_pad((string) $sequence->position, 2, '0', STR_PAD_LEFT), 48, $y + 7, 15, self::WHITE, 'bold');
            $this->drawLines($pdf, $titleLines, 84, $y + 5, 15, 354, self::NAVY, 'bold', 18);
            $pdf->textRight(($sequence->debut_hm ?: '—').'  /  '.($sequence->duree_hm ?: '00:00'),
                554, $y + 10, 10.5, self::BLUE, 'bold');
            $pdf->line(40, $y + $headerHeight - 5, 554, $y + $headerHeight - 5, self::BLUE, 1.5);
            $y += $headerHeight + 10;

            if ($sequence->intervenants->isNotEmpty()) {
                $guest = 'INVITÉS : '.$this->guests($sequence);
                $y = $this->pagedParagraph($pdf, $guest, null, $y, 503, 10.5, 14,
                    fn () => $this->scriptPage($pdf, $c, $draft), self::A4_H - 65,
                    '#536478');
                $y += 12;
            }

            $pdf->text('TEXTE À LIRE', 43, $y, 9.5, self::BLUE, 'bold');
            $y += 19;
            $script = $this->plainText((string) ($sequence->script_text ?? ''));

            if ($script === '') {
                $pdf->rect(40, $y - 3, 514, 29, self::LIGHT);
                $pdf->text('Aucun texte de présentation enregistré pour cette séquence.', 50, $y + 5, 11.5, self::MUTED);
                $y += 39;
            } else {
                $y = $this->pagedParagraph($pdf, $script, null, $y, 506, 14.2, 19.5,
                    fn () => $this->scriptPage($pdf, $c, $draft), self::A4_H - 68);
                $y += 13;
            }

            foreach ([
                'INDICATIONS PRÉSENTATEUR' => (string) ($sequence->notes_presentateur ?? ''),
                'TOP RÉGIE' => (string) ($sequence->note_regie ?? ''),
                'RÉSUMÉ' => (string) ($sequence->summary ?? ''),
            ] as $heading => $raw) {
                $value = $this->plainText($raw);
                if ($value === '') {
                    continue;
                }

                if ($y + 54 > self::A4_H - 65) {
                    $y = $this->scriptPage($pdf, $c, $draft);
                }
                $pdf->rect(40, $y, 4, 20, $heading === 'TOP RÉGIE' ? self::RED : self::BLUE);
                $pdf->text($heading, 53, $y + 2, 10, $heading === 'TOP RÉGIE' ? self::RED : self::BLUE, 'bold');
                $y += 26;
                $y = $this->pagedParagraph($pdf, $value, null, $y, 500, 11.7, 16,
                    fn () => $this->scriptPage($pdf, $c, $draft), self::A4_H - 66, self::MUTED);
                $y += 19;
            }
            $y += 15;
        }

        if ($count === 0) {
            $pdf->rect(40, $y, 514, 75, self::LIGHT);
            $pdf->text('Aucune séquence enregistrée dans cette édition.', 55, $y + 27, 13, self::MUTED);
        }
    }

    private function scriptPage(TvAosPdfDocument $pdf, Conducteur $c, bool $draft, bool $first = false): float
    {
        $pdf->newPage(self::A4_W, self::A4_H);
        $pdf->rect(0, 0, self::A4_W, $first ? 142 : 104, self::NAVY);
        $this->brand($pdf, 40, 19, 22);
        $pdf->textRight('SCRIPT PRÉSENTATEUR', 554, 22, 10.5, '#D4E4FF', 'bold');
        $name = $this->emissionName($c);
        foreach (array_slice($pdf->wrap($name, 510, 25, 'bold'), 0, 2) as $i => $line) {
            $pdf->text($line, 40, 54 + 27 * $i, $first ? 25 : 18, self::WHITE, 'bold');
        }

        if ($draft) {
            $pdf->text('BROUILLON', 145, 478, 55, '#ECF0F5', 'bold', 35);
        }

        if ($first) {
            $pdf->text('ÉDITION : '.($c->date_edition?->format('d/m/Y') ?? '—'), 41, 116, 10, '#D8E8FF', 'bold');
            $cards = [
                ['PRÉSENTATEUR', $this->presenter($c)],
                ['DURÉE', (string) ($c->total_duration_hm ?: '00:00')],
                ['STATUT', $c->status?->label() ?? '—'],
            ];
            foreach ($cards as $i => [$label, $value]) {
                $x = 40 + $i * 173;
                $pdf->rect($x, 155, 167, 51, self::LIGHT);
                $pdf->text($label, $x + 10, 163, 8.3, self::MUTED, 'bold');
                $pdf->text($this->oneLine($value, 25), $x + 10, 179, 13, self::NAVY, 'bold');
            }

            return 231;
        }

        $pdf->text('Édition du '.($c->date_edition?->format('d/m/Y') ?? '—').'   ·   Suite de lecture',
            41, 115, 10, self::MUTED);

        return 148;
    }

    /**
     * Render a long label and its text, breaking across pages without clipping.
     *
     * @param callable():float $nextPage
     */
    private function pagedParagraph(
        TvAosPdfDocument $pdf,
        string $value,
        ?string $label,
        float $y,
        float $width,
        float $fontSize,
        float $lineHeight,
        callable $nextPage,
        float $bottom,
        string $color = self::TEXT,
    ): float {
        if ($label !== null) {
            if ($y + 38 > $bottom) {
                $y = $nextPage();
            }
            $pdf->text(mb_strtoupper($label), 43, $y, 9, self::BLUE, 'bold');
            $y += 17;
        }

        foreach ($pdf->wrap($value, $width, $fontSize) as $line) {
            if ($y + $lineHeight > $bottom) {
                $y = $nextPage();
            }
            if ($line !== '') {
                $pdf->text($line, 44, $y, $fontSize, $color);
            }
            $y += $lineHeight;
        }

        return $y;
    }

    /** @param list<string> $lines */
    private function drawLines(
        TvAosPdfDocument $pdf,
        array $lines,
        float $x,
        float $y,
        float $fontSize,
        float $width,
        string $color,
        string $weight = 'regular',
        float $lineHeight = 12,
    ): void {
        foreach ($lines as $line) {
            if ($line !== '') {
                $pdf->text($line, $x, $y, $fontSize, $color, $weight);
            }
            $y += $lineHeight;
        }
    }

    private function brand(TvAosPdfDocument $pdf, float $x, float $y, float $size, string $color = self::WHITE): void
    {
        $pdf->text('TV', $x, $y, $size, $color, 'bold');
        $plusX = $x + $pdf->textWidth('TV', $size, 'bold');
        $pdf->text('+', $plusX, $y, $size, $color === self::WHITE ? '#75B3FF' : self::BLUE, 'bold');
        $pdf->text('A OS', $plusX + $pdf->textWidth('+', $size, 'bold'), $y, $size, $color, 'bold');
    }

    private function plainText(string $html): string
    {
        $text = preg_replace('/<\s*br\s*\/?>/iu', "\n", $html) ?? $html;
        $text = preg_replace('/<\s*li(?:\s+[^>]*)?>/iu', "\n• ", $text) ?? $text;
        $text = preg_replace('/<\s*\/(p|div|h[1-6]|li|blockquote)>/iu', "\n", $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function guests(Sequence $sequence): string
    {
        $names = $sequence->intervenants
            ->map(static fn ($person): string => trim(($person->prenom ?? '').' '.($person->nom ?? '')))
            ->filter()
            ->implode(', ');

        return $names !== '' ? $names : ($sequence->nom_prenom_text ?: '—');
    }

    private function presenter(Conducteur $c): string
    {
        $person = $c->presentateur;

        return $person ? trim(($person->prenom ?? '').' '.($person->nom ?? '')) : 'Non assigné';
    }

    private function emissionName(Conducteur $c): string
    {
        return (string) ($c->emission?->nom ?: $c->nom ?: 'Émission sans titre');
    }

    private function startTime(Conducteur $c): string
    {
        return ConducteurCalculatorService::formatHeure(
            $c->heure_debut_emission_calc ?: $c->emission?->heure_debut
        ) ?: '—';
    }

    private function clock(int $seconds): string
    {
        return sprintf('%02d:%02d', intdiv(max(0, $seconds), 60), max(0, $seconds) % 60);
    }

    private function oneLine(string $text, int $limit): string
    {
        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit - 1).'…' : $text;
    }
}
