<?php

namespace App\Support;

use Dompdf\Dompdf;
use FontLib\Font;

class G9CertificateTitle
{
    /** Same circular geometry as g9CertificateTitle.js; units can be pixels or points. */
    public static function arc(array $widths, float $available, float $curve = 26): array
    {
        $total = array_sum($widths);
        if ($total <= 0) return [];
        $sweep = deg2rad(max(0, min(90, $curve)));
        if ($sweep === 0.0) {
            $left = ($available - $total) / 2;
            $positions = [];
            foreach ($widths as $width) {
                $positions[] = ['left'=>$left, 'top'=>0, 'rotation'=>0, 'width'=>$width];
                $left += $width;
            }
            return $positions;
        }
        $radius = $total / $sweep;
        $advance = 0;
        $positions = [];
        foreach ($widths as $width) {
            $angle = (($advance + $width / 2) / $total - .5) * $sweep;
            $advance += $width;
            $positions[] = ['left'=>$available / 2 + $radius * sin($angle) - $width / 2,
                'top'=>2 * $radius * sin($angle / 2) ** 2, 'rotation'=>rad2deg($angle), 'width'=>$width];
        }
        return $positions;
    }

    public static function widths(array $characters, string $fontKey, float $size, bool $bold): array
    {
        static $metrics = [];
        $definition = G9CertificateTypography::fonts()[$fontKey];
        if (isset($definition['file'])) {
            if (!isset($metrics[$fontKey])) {
                $font = Font::load(public_path(G9CertificateTypography::fontPath($definition)));
                try {
                    $metrics[$fontKey] = ['map'=>$font->getUnicodeCharMap(), 'widths'=>$font->getData('hmtx'),
                        'units'=>$font->getData('head', 'unitsPerEm')];
                } finally {
                    $font->close();
                }
            }
            $data = $metrics[$fontKey];
            return array_map(function ($character) use ($data, $size) {
                $width = 0;
                foreach (mb_str_split($character) as $codepoint) {
                    $glyph = $data['map'][mb_ord($codepoint)] ?? 0;
                    $width += $data['widths'][$glyph][0] ?? 0;
                }
                return $size * $width / $data['units'];
            }, $characters);
        }
        // Built-in PDF fonts use their own metrics, just as the other certificate text does.
        static $core;
        $core ??= (new Dompdf)->getFontMetrics();
        $family = $fontKey === 'times' ? 'Times' : 'Helvetica';
        $font = $core->getFont($family, $bold ? 'bold' : 'normal');
        return array_map(fn ($character) => $core->getTextWidth($character, $font, $size), $characters);
    }
}
