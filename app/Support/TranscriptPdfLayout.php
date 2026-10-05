<?php

namespace App\Support;

use Dompdf\Dompdf;
use FontLib\Font;

class TranscriptPdfLayout
{
    public static function configure(Dompdf $dompdf): void
    {
        $prepared = false;
        $fontBaselines = [];

        $dompdf->setCallbacks([[
            'event' => 'begin_page_reflow',
            'f' => static function ($page, $canvas, $fontMetrics) use (&$prepared, &$fontBaselines): void {
                // Prepare the whole tree once, before pagination splits it into pages.
                if ($prepared) return;
                $prepared = true;

                foreach ($page->get_subtree() as $field) {
                    $node = $field->get_node();
                    if (!$node instanceof \DOMElement) continue;
                    $classes = preg_split('/\s+/', $node->getAttribute('class'));
                    if (!array_intersect($classes, ['transcript-cover-field', 'transcript-content-field'])) continue;

                    $style = $field->get_style();
                    $font = $style->font_family;
                    $size = $style->font_size;
                    $lineHeight = $style->line_height;
                    $width = (float) $style->length_in_pt($style->width, $canvas->get_width());
                    $textWidth = $fontMetrics->getTextWidth(trim($node->textContent), $font, $size);
                    if ($width > 0 && $textWidth > $width) {
                        // DomPDF cannot run the browser's name-fitting JavaScript.
                        // Fit each value to its printed row, including long addresses.
                        $scale = $width / $textWidth;
                        $size *= $scale;
                        $lineHeight *= $scale;
                    }

                    if (!isset($fontBaselines[$font]) && is_file($font . '.ttf')) {
                        $fontFile = Font::load($font . '.ttf');
                        $units = $fontFile->getData('head', 'unitsPerEm');
                        $fontBaselines[$font] = ($fontFile->getData('hhea', 'ascent') + $fontFile->getData('hhea', 'descent')) / $units;
                        $fontFile->close();
                    }

                    if (isset($fontBaselines[$font])) {
                        // Browsers center the font's ascent/descent in the CSS line box.
                        // DomPDF instead multiplies line-height by the font height and
                        // positions the baseline at 80% of that box (Block::vertical_align).
                        // Normalize that box to the same baseline, without changing top.
                        $baseline = ($lineHeight + $size * $fontBaselines[$font]) / 2;
                        $fontHeight = $fontMetrics->getFontHeight($font, $size);
                        if ($fontHeight > 0) $lineHeight = $baseline * $size / (0.8 * $fontHeight);
                    }

                    foreach ($field->get_subtree() as $child) {
                        $childStyle = $child->get_style();
                        $childStyle->set_prop('font_size', $size . 'pt');
                        $childStyle->set_prop('line_height', $lineHeight . 'pt');
                        $childStyle->set_prop('white_space', 'nowrap');
                    }
                }
            },
        ]]);
    }
}
