<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Conducteur;
use App\Models\Sequence;

/**
 * Génère un PDF imprimable : script présentateur (chronologie, notes, intervenants)
 * + données complètes du conducteur d'émission.
 *
 * Générateur PDF minimal (sans dépendance).
 */
final class ConducteurPdfExporter
{
    private const PAGE_W = 595; // A4

    private const PAGE_H = 842;

    private const MARGIN = 40;

    private const LINE_HEIGHT = 14;

    private const MAX_CHARS_PER_LINE = 92;

    private const MAX_LINES_PER_PAGE = 40;

    /** @var list<array{text: string, bold: bool}> */
    private array $lines = [];

    public function export(Conducteur $conducteur): string
    {
        $this->buildLines($conducteur);

        return $this->renderPdf();
    }

    private function buildLines(Conducteur $c): void
    {
        $this->addLine("Conducteur d'émission - ".($c->nom ?? '(sans nom)'), true);
        $this->addLine('Émission : '.($c->emission?->nom ?? '(sans émission)'));
        $this->addLine('Code : '.($c->code_conducteur ?? ''));
        $this->addLine('Date d\'édition : '.($c->date_edition?->format('d/m/Y') ?? ''));
        $this->addLine('Statut : '.($c->status?->label() ?? '(non renseigné)'));
        $this->addLine('Présentateur : '.($this->nomCompletIntervenant($c->presentateur) !== '' ? $this->nomCompletIntervenant($c->presentateur) : '(non renseigné)'));
        $this->addLine('Durée totale : '.($c->total_duration_hm ?: '00:00').' / Cible : '.($c->target_duration_minutes ? $c->target_duration_minutes.' min' : '(non renseignée)'));

        $sequences = $c->sequences()->with(['intervenants'])->orderBy('position')->get();

        $this->addLine('', true);
        $this->addLine('Script présentateur - Chronologie et notes', true);
        foreach ($sequences as $sequence) {
            $this->addSequenceLines($sequence);
        }

        $this->addLine('', true);
        $this->addLine('Données complètes du conducteur - Séquences et statuts', true);
        $this->addLine('Nom du conducteur : '.($c->nom ?? ''));
        $this->addLine('Type : emission');
        $this->addLine('Statut : '.($c->status?->label() ?? '(non renseigné)'));
        $this->addLine('Durée totale : '.($c->total_duration_hm ?: '00:00'));
        $this->addLine('Cible : '.($c->target_duration_minutes ? $c->target_duration_minutes.' min' : '(non renseignée)'));
        $this->addLine('Date d\'édition : '.($c->date_edition?->format('d/m/Y') ?? ''));
        $this->addLine('Séquence active : '.($c->active_sequence_id ? '#'.$c->active_sequence_id : '(aucune)'));
        $this->addLine('Antenne : début '.($c->antenne_started_at?->format('d/m H:i') ?? '(non démarrée)').' · fin '.($c->antenne_ended_at?->format('d/m H:i') ?? '(ouverte)').' · décalage '.ConducteurCalculatorService::formatDuree((int) ($c->antenne_offset_sec ?? 0)));
        $this->addLine('Émission : '.($c->emission?->nom ?? ''));

        $this->addLine('', true);
        $this->addLine('Détail des séquences', true);
        foreach ($sequences as $sequence) {
            $this->addSequenceLines($sequence);
        }
    }

    private function addSequenceLines(Sequence $sequence): void
    {
        $interv = $sequence->intervenants?->map(fn ($i): string => trim($this->nomCompletIntervenant($i)))->filter()->implode(', ');

        $this->addLine('#'.$sequence->position.' — '.$sequence->title, true);
        $this->addLine('  Type : '.($sequence->type?->label() ?? '').' · Duree : '.$sequence->duree_hm.' · Heure : '.$sequence->heure_debut_calculee.' → '.$sequence->heure_fin_calculee);
        $this->addLine('  Statut : '.($sequence->statut_presentation?->label() ?? '(non renseigné)'));
        if (($sequence->notes_presentateur ?? '') !== '') {
            $this->addLine('  Notes présentateur : '.$sequence->notes_presentateur);
        }
        if (($sequence->summary ?? '') !== '') {
            $this->addLine('  Résumé : '.$sequence->summary);
        }
        if ($interv !== '') {
            $this->addLine('  Intervenant(s) : '.$interv);
        }
    }

    private function addLine(string $text, bool $bold = false): void
    {
        foreach ($this->wrap($text) as $line) {
            $this->lines[] = ['text' => $line, 'bold' => $bold];
        }
    }

    /** @return list<string> */
    private function wrap(string $text): array
    {
        $lines = [];
        foreach (explode("\n", $text) as $para) {
            if ($para === '') {
                $lines[] = '';

                continue;
            }
            $current = '';
            foreach (explode(' ', $para) as $word) {
                if ($word === '') {
                    continue;
                }
                if ($current === '') {
                    $current = $word;
                } elseif (mb_strlen($current.' '.$word) <= self::MAX_CHARS_PER_LINE) {
                    $current .= ' '.$word;
                } else {
                    $lines[] = $current;
                    $current = $word;
                }
                while (mb_strlen($current) > self::MAX_CHARS_PER_LINE) {
                    $lines[] = mb_substr($current, 0, self::MAX_CHARS_PER_LINE);
                    $current = mb_substr($current, self::MAX_CHARS_PER_LINE);
                }
            }
            $lines[] = $current;
        }

        return $lines;
    }

    private function renderPdf(): string
    {
        $pages = array_chunk($this->lines, self::MAX_LINES_PER_PAGE);
        if ($pages === []) {
            $pages = [[]];
        }

        $objects = [];
        $nextId = 1;
        $catalogId = $nextId++;
        $pagesId = $nextId++;
        $pageIds = [];
        $contentIds = [];
        foreach ($pages as $i => $lines) {
            $pageIds[] = $nextId++;
            $contentIds[] = $nextId++;
        }
        $fontId = $nextId++;
        $fontBoldId = $nextId++;

        $objects[$catalogId] = '<< /Type /Catalog /Pages '.$pagesId.' 0 R >>';
        $objects[$pagesId] = '<< /Type /Pages /Kids ['.implode(' ', array_map(fn ($id): string => "$id 0 R", $pageIds)).'] /Count '.count($pageIds).' >>';

        foreach ($pages as $i => $lines) {
            $objects[$pageIds[$i]] = sprintf(
                '<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %d %d] /Resources << /Font << /F1 %d 0 R /F2 %d 0 R >> >> /Contents %d 0 R >>',
                $pagesId,
                self::PAGE_W,
                self::PAGE_H,
                $fontId,
                $fontBoldId,
                $contentIds[$i]
            );
            $content = $this->renderContent($lines, $fontId, $fontBoldId);
            $objects[$contentIds[$i]] = '<< /Length '.strlen($content).' >>'.\PHP_EOL.'stream'.\PHP_EOL.$content.\PHP_EOL.'endstream';
        }

        $objects[$fontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /ISO-8859-1 >>';
        $objects[$fontBoldId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /ISO-8859-1 >>';

        return $this->assemble($objects, $catalogId);
    }

    /**
     * @param  list<array{text: string, bold: bool}>  $lines
     */
    private function renderContent(array $lines, int $fontId, int $fontBoldId): string
    {
        $out = '';
        $y = self::PAGE_H - self::MARGIN;
        foreach ($lines as $line) {
            $y -= self::LINE_HEIGHT;
            $font = $line['bold'] ? $fontBoldId : $fontId;
            $out .= sprintf("BT /F%d 12 Tf 1 0 0 1 %d %d Tm (%s) Tj ET\n", $font, self::MARGIN, (int) round($y), $this->escapePdf($line['text']));
        }

        return $out;
    }

    /** @param array<int, string> $objects */
    private function assemble(array $objects, int $rootId): string
    {
        ksort($objects);
        $out = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($out);
            $out .= $id." 0 obj\n".$body."\nendobj\n";
        }
        $size = max(array_keys($objects)) + 1;
        $xref = strlen($out);
        $out .= "xref\n0 $size\n";
        $out .= "0000000000 65535 f \n";
        for ($id = 1; $id < $size; $id++) {
            $out .= str_pad((string) ($offsets[$id] ?? 0), 10, '0', STR_PAD_LEFT).' 00000 n '."\n";
        }
        $out .= "trailer\n<< /Size $size /Root $rootId 0 R >>\nstartxref\n$xref\n%%EOF";

        return $out;
    }

    private function escapePdf(string $text): string
    {
        // Fallback pour les caractères hors ISO-8859-1 (tirets, guillemets, apostrophes courbes).
        $text = str_replace(['—', '–', '→', '’', '“', '”'], ['-', '-', '->', "'", '"', '"'], $text);
        $text = mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) $text);
    }

    private function nomCompletIntervenant($intervenant): string
    {
        if ($intervenant === null) {
            return '';
        }
        $civilite = $intervenant->civilite;
        if (is_object($civilite) && method_exists($civilite, 'label')) {
            $civilite = $civilite->label();
        }
        $civilite = $civilite === null ? '' : (string) $civilite;

        return trim($civilite.' '.($intervenant->prenom ?? '').' '.($intervenant->nom ?? ''));
    }
}
