<?php

namespace App\Support;

use Dompdf\Dompdf;
use Mpdf\Cache;
use Mpdf\Fonts\FontCache;
use Mpdf\Mpdf;
use Mpdf\Otl;

class TranscriptPdfRenderer
{
    /** Keep the established template layout while shaping Khmer with OpenType GSUB/GPOS. */
    public static function save(Dompdf $layout, string $path, string $tempDir): array
    {
        // Only the first page of each student's book contains dynamic fields. Lay out
        // those pages; draw all remaining template pages directly in the final PDF.
        // This avoids retaining hundreds of redundant image pages in DomPDF's canvas.
        $dom = $layout->getDom();
        $xpath = new \DOMXPath($dom);
        $sections = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " transcript-template-page ")]');
        $images = [];
        $layoutPages = [];
        foreach ($sections as $index => $section) {
            $image = $xpath->query('./img', $section)->item(0);
            if (!$image) continue;
            $page = $index + 1;
            $images[$page] = $image->getAttribute('src');
            $values = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " transcript-cover-field ") or contains(concat(" ", normalize-space(@class), " "), " transcript-content-field ")]', $section);
            if ($values->length) $layoutPages[] = $page;
            else $section->parentNode->removeChild($section);
        }
        if ($images) $layout->loadHtml($dom->saveHTML(), 'UTF-8');
        unset($dom, $xpath, $sections, $section, $image, $values);
        TranscriptPdfLayout::configure($layout);
        $prepare = $layout->getCallbacks()['begin_page_reflow'][0];
        $fields = [];
        $layout->setCallbacks([
            ['event' => 'begin_page_reflow', 'f' => $prepare],
            ['event' => 'begin_frame', 'f' => static function ($frame, $canvas, $metrics) use ($layoutPages, &$fields): void {
                $node = $frame->get_node();
                $page = $layoutPages[$canvas->get_page_number() - 1] ?? $canvas->get_page_number();
                if (!$frame->is_text_node() || trim($node->textContent) === '') return;
                $parent = $frame->get_parent();
                $element = $parent->get_node();
                if (!$element instanceof \DOMElement) return;
                $classes = preg_split('/\s+/', $element->getAttribute('class'));
                if (!array_intersect($classes, ['transcript-cover-field', 'transcript-content-field'])) return;
                $style = $frame->get_style();
                $fieldStyle = $parent->get_style();
                $fields[$page][] = [
                    'text' => trim($element->textContent),
                    'class' => end($classes),
                    'font' => $style->font_family === $metrics->getFont('Khmer OS Muol Light', 'normal') ? 'transcriptmuol' : 'transcriptsiemreap',
                    'size' => (float) $style->font_size,
                    'left' => $parent->get_position('x'),
                    'width' => (float) $fieldStyle->length_in_pt($fieldStyle->width, $canvas->get_width()),
                    'align' => $fieldStyle->text_align,
                    'baseline' => $frame->get_position('y') + $metrics->getFontBaseline($style->font_family, $style->font_size),
                ];
            }],
        ]);
        $layout->render();
        if (!$images) {
            file_put_contents($path, $layout->output());
            return [];
        }
        $pdf = new Mpdf([
            'mode' => 'utf-8', 'format' => 'A4-L', 'tempDir' => $tempDir,
            'margin_left' => 0, 'margin_right' => 0, 'margin_top' => 0, 'margin_bottom' => 0,
            'fontDir' => [public_path('fonts/khmer')],
            'fontdata' => [
                'transcriptmuol' => ['R' => 'KhmerOSmuollight.ttf', 'useOTL' => 0xFF],
                'transcriptsiemreap' => ['R' => 'KhmerOSsiemreap.ttf', 'useOTL' => 0xFF],
            ],
            'default_font' => 'transcriptsiemreap',
        ]);
        $pdf->SetTitle('Stu. Transcript Book');
        $pdf->SetAutoPageBreak(false);
        $shaper = new Otl($pdf, new FontCache(new Cache($tempDir.'/mpdf/ttfontdata')));
        $rendered = [];
        foreach ($images as $page => $image) {
            $pdf->AddPage('L');
            $imagePath = preg_replace('~^file:///?~', '', $image);
            if (PHP_OS_FAMILY !== 'Windows' && !str_starts_with($imagePath, '/')) $imagePath = '/'.$imagePath;
            $pdf->Image(rawurldecode($imagePath), 0, 0, 297, 210, 'jpg');
            foreach ($fields[$page] ?? [] as $field) {
                $pdf->SetFont($field['font'], '', $field['size']);
                $shaped = $shaper->applyOTL($field['text'], 0xFF);
                $glyphs = $shaper->OTLdata;
                $width = $pdf->GetStringWidth($shaped, true, $glyphs);
                $available = $field['width'] / Mpdf::SCALE;
                if ($width > $available && $width > 0) {
                    $pdf->SetFontSize($field['size'] * $available / $width);
                    $width = $available;
                }
                $left = $field['left'] / Mpdf::SCALE;
                if ($field['align'] === 'center') $left += ($available - $width) / 2;
                elseif ($field['align'] === 'right') $left += $available - $width;
                $pdf->Text($left, $field['baseline'] / Mpdf::SCALE, $shaped, $glyphs);
                $rendered[$page][$field['class']] = $field + ['shaped' => $shaped, 'rendered_width' => $width * Mpdf::SCALE];
            }
        }
        $pdf->Output($path, \Mpdf\Output\Destination::FILE);
        return $rendered;
    }
}
