<?php

declare(strict_types=1);

namespace App\Services\Pdf;

/**
 * Minimal vector PDF writer for the LWS Laravel application.
 *
 * Both Pilat Narrow fonts are embedded with ToUnicode mappings; no browser,
 * shell command or new Composer dependency is required on the host.
 */
final class TvAosPdfDocument
{
    /** @var list<array{width:float,height:float,commands:list<string>}> */
    private array $pages = [];

    /** @var array<string,TvAosTrueTypeFont> */
    private array $fonts;

    private int $current = -1;

    public function __construct()
    {
        $this->fonts = [
            'regular' => new TvAosTrueTypeFont(public_path('fonts/PilatNarrow-Book.ttf')),
            'bold' => new TvAosTrueTypeFont(public_path('fonts/PilatNarrow-Bold.ttf')),
        ];
    }

    public function newPage(float $width, float $height): int
    {
        $this->pages[] = ['width' => $width, 'height' => $height, 'commands' => []];
        $this->current = count($this->pages) - 1;
        $this->rect(0, 0, $width, $height, '#FFFFFF');

        return $this->current;
    }

    public function pageIndex(): int
    {
        return $this->current;
    }

    public function pageCount(): int
    {
        return count($this->pages);
    }

    public function usePage(int $page): void
    {
        $this->current = $page;
    }

    public function pageSize(int $page): array
    {
        return [$this->pages[$page]['width'], $this->pages[$page]['height']];
    }

    public function rect(float $x, float $top, float $width, float $height, string $fill): void
    {
        $page = $this->pages[$this->current];
        $bottom = $page['height'] - $top - $height;
        $this->command($this->rgb($fill).' rg '
            .$this->num($x).' '.$this->num($bottom).' '
            .$this->num($width).' '.$this->num($height).' re f');
    }

    public function line(
        float $x1,
        float $top1,
        float $x2,
        float $top2,
        string $stroke = '#E4E9F0',
        float $weight = 1,
    ): void {
        $page = $this->pages[$this->current];
        $this->command($this->rgb($stroke).' RG '.$this->num($weight).' w '
            .$this->num($x1).' '.$this->num($page['height'] - $top1).' m '
            .$this->num($x2).' '.$this->num($page['height'] - $top2).' l S');
    }

    public function text(
        string $text,
        float $x,
        float $top,
        float $size = 11,
        string $color = '#181C1E',
        string $font = 'regular',
        float $angle = 0,
    ): void {
        if ($text === '') {
            return;
        }

        $fontKey = $font === 'bold' ? 'bold' : 'regular';
        $usedFont = $this->fonts[$fontKey];
        $glyphs = '';

        foreach (preg_split('//u', mb_convert_encoding($text, 'UTF-8', 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $glyphs .= sprintf('%04X', $usedFont->codeFor($character));
        }
        if ($glyphs === '') {
            return;
        }

        $page = $this->pages[$this->current];
        $rad = deg2rad($angle);
        $a = cos($rad);
        $b = sin($rad);
        $c = -sin($rad);
        $d = cos($rad);

        $this->command('BT /'.($fontKey === 'bold' ? 'F2' : 'F1').' '.$this->num($size).' Tf '
            .$this->rgb($color).' rg '
            .$this->num($a).' '.$this->num($b).' '.$this->num($c).' '.$this->num($d).' '
            .$this->num($x).' '.$this->num($page['height'] - $top - $size * 0.82)
            .' Tm <'.$glyphs.'> Tj ET');
    }

    public function textRight(
        string $text,
        float $right,
        float $top,
        float $size = 11,
        string $color = '#181C1E',
        string $font = 'regular',
    ): void {
        $this->text($text, $right - $this->textWidth($text, $size, $font), $top, $size, $color, $font);
    }

    public function textWidth(string $text, float $size, string $font = 'regular'): float
    {
        $usedFont = $this->fonts[$font === 'bold' ? 'bold' : 'regular'];
        $units = 0;

        foreach (preg_split('//u', mb_convert_encoding($text, 'UTF-8', 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $units += $usedFont->width($usedFont->codeFor($character));
        }

        return $units * $size / 1000;
    }

    /** @return list<string> */
    public function wrap(string $text, float $maxWidth, float $size, string $font = 'regular'): array
    {
        $result = [];

        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $text)) as $paragraph) {
            if (trim($paragraph) === '') {
                $result[] = '';
                continue;
            }

            $line = '';
            foreach (preg_split('/\s+/u', trim($paragraph), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                $proposed = $line === '' ? $word : $line.' '.$word;
                if ($this->textWidth($proposed, $size, $font) <= $maxWidth) {
                    $line = $proposed;
                    continue;
                }

                if ($line !== '') {
                    $result[] = $line;
                    $line = '';
                }

                foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
                    if ($line !== '' && $this->textWidth($line.$character, $size, $font) > $maxWidth) {
                        $result[] = $line;
                        $line = '';
                    }
                    $line .= $character;
                }
            }
            if ($line !== '') {
                $result[] = $line;
            }
        }

        return $result;
    }

    public function save(string $title = 'TV+A OS'): string
    {
        $objects = [];
        $nextId = 0;
        $allocate = static function (string $value = '') use (&$objects, &$nextId): int {
            $id = ++$nextId;
            $objects[$id] = $value;

            return $id;
        };

        $catalogId = $allocate();
        $pagesId = $allocate();
        $fontRefs = [];

        foreach ($this->fonts as $key => $font) {
            $fontFileId = $allocate($this->stream($font->rawData(), '/Length1 '.strlen($font->rawData())));
            $descriptorId = $allocate();
            $cidId = $allocate();
            $unicodeId = $allocate($this->stream($this->toUnicode($font), ''));
            $type0Id = $allocate();

            $fontName = $key === 'bold' ? 'TVAOSPilatBold' : 'TVAOSPilatBook';
            $objects[$descriptorId] = '<< /Type /FontDescriptor /FontName /'.$fontName
                .' /Flags 32 /FontBBox [-300 -350 1300 1100] /ItalicAngle 0'
                .' /Ascent 850 /Descent -250 /CapHeight 720 /StemV 80'
                .' /FontFile2 '.$fontFileId.' 0 R >>';

            $widths = [];
            foreach ($font->usedGlyphs() as $gid => $cp) {
                $widths[] = (int) $gid.' ['.$this->num($font->width((int) $gid)).']';
            }
            $objects[$cidId] = '<< /Type /Font /Subtype /CIDFontType2 /BaseFont /'.$fontName
                .' /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >>'
                .' /FontDescriptor '.$descriptorId.' 0 R /DW 1000'
                .' /W ['.implode(' ', $widths).'] /CIDToGIDMap /Identity >>';
            $objects[$type0Id] = '<< /Type /Font /Subtype /Type0 /BaseFont /'.$fontName
                .' /Encoding /Identity-H /DescendantFonts ['.$cidId.' 0 R]'
                .' /ToUnicode '.$unicodeId.' 0 R >>';
            $fontRefs[$key] = $type0Id;
        }

        $pageIds = [];

        foreach ($this->pages as $page) {
            $pageId = $allocate();
            $pageIds[] = $pageId;
            $contentId = $allocate($this->stream(implode("\n", $page['commands']), ''));
            $objects[$pageId] = '<< /Type /Page /Parent '.$pagesId.' 0 R'
                .' /MediaBox [0 0 '.$this->num($page['width']).' '.$this->num($page['height']).']'
                .' /Resources << /Font << /F1 '.$fontRefs['regular'].' 0 R /F2 '
                .$fontRefs['bold'].' 0 R >> >> /Contents '.$contentId.' 0 R >>';
        }

        $objects[$pagesId] = '<< /Type /Pages /Count '.count($pageIds).' /Kids ['
            .implode(' ', array_map(static fn (int $id) => $id.' 0 R', $pageIds)).'] >>';
        $infoId = $allocate('<< /Title <FEFF'.strtoupper(bin2hex(mb_convert_encoding($title, 'UTF-16BE', 'UTF-8'))).'>'
            .' /Creator (TV+A OS - Laravel) /Producer (TV+A OS PDF) >>');
        $objects[$catalogId] = '<< /Type /Catalog /Pages '.$pagesId.' 0 R >>';

        ksort($objects);
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($out);
            $out .= $id." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($out);
        $out .= "xref\n0 ".($nextId + 1)."\n0000000000 65535 f \n";
        for ($i = 1; $i <= $nextId; $i++) {
            $out .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        $out .= "trailer\n<< /Size ".($nextId + 1).' /Root '.$catalogId.' 0 R /Info '
            .$infoId." 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return $out;
    }

    private function toUnicode(TvAosTrueTypeFont $font): string
    {
        $pairs = [];
        foreach ($font->usedGlyphs() as $gid => $codepoint) {
            $unicode = mb_chr($codepoint, 'UTF-8');
            $pairs[] = '<'.sprintf('%04X', $gid).'> <'
                .strtoupper(bin2hex(mb_convert_encoding($unicode, 'UTF-16BE', 'UTF-8'))).'>';
        }

        $out = "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n"
            ."/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n"
            ."/CMapName /TVAOSUnicode def\n/CMapType 2 def\n"
            ."1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n";

        foreach (array_chunk($pairs, 100) as $chunk) {
            $out .= count($chunk)." beginbfchar\n".implode("\n", $chunk)."\nendbfchar\n";
        }

        return $out."endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend\n";
    }

    private function stream(string $data, string $extra): string
    {
        $compressed = gzcompress($data, 6);

        return '<< /Length '.strlen($compressed).' /Filter /FlateDecode '.$extra
            ." >>\nstream\n".$compressed."\nendstream";
    }

    private function command(string $line): void
    {
        $this->pages[$this->current]['commands'][] = $line;
    }

    private function num(float $value): string
    {
        return rtrim(rtrim(sprintf('%.3F', $value), '0'), '.') ?: '0';
    }

    private function rgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            $hex = '181C1E';
        }

        return implode(' ', [
            $this->num(hexdec(substr($hex, 0, 2)) / 255),
            $this->num(hexdec(substr($hex, 2, 2)) / 255),
            $this->num(hexdec(substr($hex, 4, 2)) / 255),
        ]);
    }
}
