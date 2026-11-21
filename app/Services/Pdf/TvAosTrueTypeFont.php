<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use RuntimeException;

/**
 * Small TrueType reader used by the dependency-free PDF renderer.
 *
 * Supports the Unicode cmap format 4/12 tables, glyph advance widths and
 * an embedded full font with a ToUnicode map. Exported text remains searchable,
 * selectable and correctly accented.
 */
final class TvAosTrueTypeFont
{
    private string $data;
    private int $unitsPerEm;
    private int $hmtxOffset;
    private int $numberOfHMetrics;
    private int $cmapOffset = 0;
    private int $cmapFormat = 0;

    /** @var array<int, int> Glyph ID to Unicode code point. */
    private array $used = [];

    /** @var array<int, int> */
    private array $glyphCache = [];

    public function __construct(string $path)
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Police PDF introuvable : '.$path);
        }

        $this->data = (string) file_get_contents($path);
        $tables = [];
        $count = $this->u16(4);

        for ($i = 0; $i < $count; $i++) {
            $offset = 12 + 16 * $i;
            $tables[substr($this->data, $offset, 4)] = [
                'offset' => $this->u32($offset + 8),
                'length' => $this->u32($offset + 12),
            ];
        }

        foreach (['head', 'hhea', 'hmtx', 'cmap'] as $tag) {
            if (! isset($tables[$tag])) {
                throw new RuntimeException('Police PDF invalide : table '.$tag.' manquante.');
            }
        }

        $this->unitsPerEm = max(1, $this->u16($tables['head']['offset'] + 18));
        $this->numberOfHMetrics = max(1, $this->u16($tables['hhea']['offset'] + 34));
        $this->hmtxOffset = $tables['hmtx']['offset'];

        $cmap = $tables['cmap']['offset'];
        $subtables = $this->u16($cmap + 2);
        $score = -1;

        for ($i = 0; $i < $subtables; $i++) {
            $entry = $cmap + 4 + 8 * $i;
            $platform = $this->u16($entry);
            $encoding = $this->u16($entry + 2);
            $tableOffset = $cmap + $this->u32($entry + 4);
            $format = $this->u16($tableOffset);
            if (! in_array($format, [4, 12], true)) {
                continue;
            }

            $rank = ($platform === 3 && $encoding === 10 && $format === 12) ? 100
                : (($platform === 3 && $encoding === 1 && $format === 4) ? 90
                : (($platform === 0) ? 80 : 10));
            if ($rank > $score) {
                $score = $rank;
                $this->cmapOffset = $tableOffset;
                $this->cmapFormat = $format;
            }
        }

        if ($this->cmapOffset === 0) {
            throw new RuntimeException('Police PDF sans table Unicode prise en charge.');
        }
    }

    public function rawData(): string
    {
        return $this->data;
    }

    public function codeFor(string $character): int
    {
        $cp = mb_ord($character, 'UTF-8');
        $gid = $this->glyphCache[$cp] ??= $this->glyphForCodepoint($cp);
        if ($gid === 0 && $cp !== 32 && $cp !== 63) {
            $cp = 63;
            $gid = $this->glyphCache[$cp] ??= $this->glyphForCodepoint($cp);
        }

        if (! isset($this->used[$gid])) {
            $this->used[$gid] = $cp;
        }

        return $gid;
    }

    public function width(int $glyphId): float
    {
        $index = min($glyphId, $this->numberOfHMetrics - 1);

        return $this->u16($this->hmtxOffset + 4 * $index) * 1000 / $this->unitsPerEm;
    }

    /** @return array<int, int> */
    public function usedGlyphs(): array
    {
        ksort($this->used);

        return $this->used;
    }

    private function glyphForCodepoint(int $cp): int
    {
        $offset = $this->cmapOffset;

        if ($this->cmapFormat === 12) {
            $groups = $this->u32($offset + 12);
            $lo = 0;
            $hi = $groups - 1;

            while ($lo <= $hi) {
                $mid = intdiv($lo + $hi, 2);
                $pos = $offset + 16 + $mid * 12;
                $start = $this->u32($pos);
                $end = $this->u32($pos + 4);

                if ($cp < $start) {
                    $hi = $mid - 1;
                } elseif ($cp > $end) {
                    $lo = $mid + 1;
                } else {
                    return $this->u32($pos + 8) + $cp - $start;
                }
            }

            return 0;
        }

        if ($cp > 0xFFFF) {
            return 0;
        }

        $segments = intdiv($this->u16($offset + 6), 2);
        $endCodes = $offset + 14;
        $startCodes = $endCodes + 2 * $segments + 2;
        $deltaCodes = $startCodes + 2 * $segments;
        $rangeOffsets = $deltaCodes + 2 * $segments;

        for ($i = 0; $i < $segments; $i++) {
            $end = $this->u16($endCodes + 2 * $i);
            if ($cp > $end) {
                continue;
            }
            $start = $this->u16($startCodes + 2 * $i);
            if ($cp < $start) {
                return 0;
            }

            $delta = $this->u16($deltaCodes + 2 * $i);
            $range = $this->u16($rangeOffsets + 2 * $i);
            if ($range === 0) {
                return ($cp + $delta) & 0xFFFF;
            }

            $gid = $this->u16($rangeOffsets + 2 * $i + $range + 2 * ($cp - $start));

            return $gid === 0 ? 0 : ($gid + $delta) & 0xFFFF;
        }

        return 0;
    }

    private function u16(int $offset): int
    {
        return unpack('n', substr($this->data, $offset, 2))[1] ?? 0;
    }

    private function u32(int $offset): int
    {
        return unpack('N', substr($this->data, $offset, 4))[1] ?? 0;
    }
}
