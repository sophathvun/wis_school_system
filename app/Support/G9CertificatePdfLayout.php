<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Dompdf\Dompdf;

class G9CertificatePdfLayout
{
    public static function configure(Dompdf $pdf): void
    {
        TranscriptPdfLayout::configure($pdf,['g9-certificate-line']);
        $normalize = $pdf->getCallbacks()['begin_page_reflow'][0];
        $prepared = false;
        $pdf->setCallbacks([['event'=>'begin_page_reflow','f'=>static function($page,$canvas,$fonts) use (&$prepared,$normalize): void {
            if (!$prepared) {
                $prepared = true;
                foreach ($page->get_subtree() as $field) {
                    $node = $field->get_node();
                    if (!$node instanceof DOMElement || !$node->hasAttribute('data-g9-font-key')) continue;
                    $size = $field->get_style()->font_size;
                    $widest = 0; $available = 0;
                    foreach ($field->get_subtree() as $line) {
                        $lineNode = $line->get_node();
                        if (!$lineNode instanceof DOMElement || !str_contains($lineNode->getAttribute('class'),'g9-certificate-line')) continue;
                        $lineStyle = $line->get_style();
                        $widest = max($widest,$fonts->getTextWidth($lineNode->textContent,$lineStyle->font_family,$size));
                        $available = max($available,(float)$lineStyle->length_in_pt($lineStyle->width,$canvas->get_width())-3);
                    }
                    if ($available>0 && $widest>$available) {
                        $size *= $available/$widest;
                        foreach ($field->get_subtree() as $child) $child->get_style()->set_prop('font_size',$size.'pt');
                    }
                }
            }
            $normalize($page,$canvas,$fonts);
        }]]);
    }

    /** Apply saved font data on the server: DomPDF cannot execute the browser module. */
    public static function prepareHtml(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($html);
            foreach ((new DOMXPath($document))->query('//*[@data-g9-layout-key]') as $field) {
                if (!$field instanceof DOMElement) continue;
                $key = $field->getAttribute('data-g9-layout-key');
                if ($key === 'qr') {
                    $width = max(8, min(20, (float) $field->getAttribute('data-g9-width')));
                    $x = max(0, min(100-$width, (float) $field->getAttribute('data-g9-x')));
                    $y = max(0, min(100-($width*297/100+3)*100/210, (float) $field->getAttribute('data-g9-y')));
                    $field->setAttribute('style', 'left:'.$x.'%;top:'.$y.'%;width:'.$width.'%');
                    foreach ($field->childNodes as $frame) {
                        if ($frame instanceof DOMElement && $frame->getAttribute('class') === 'g9-qr-frame') {
                            $frame->setAttribute('style', 'height:'.(297*$width/100).'mm');
                        }
                    }
                    continue;
                }
                $x = max(0,min(98,(float)$field->getAttribute('data-g9-x')));
                $y = max(0,min(95,(float)$field->getAttribute('data-g9-y')));
                $width = max($key==='photo'?2:5,min(100,(float)$field->getAttribute('data-g9-width')));
                $position = ';left:'.$x.'%;top:'.$y.'%;width:'.$width.'%';
                if ($key==='photo') {
                    $height = max(2,min(40,(float)$field->getAttribute('data-g9-height')));
                    $field->setAttribute('style',ltrim($position,';').';height:'.$height.'%');
                    continue;
                }
                if (!isset(G9CertificateTypography::fields()[$key])) continue;
                $style = G9CertificateTypography::resolve([$key=>[
                    'font'=>$field->getAttribute('data-g9-font-name'),
                    'size'=>$field->getAttribute('data-g9-font-size'),
                    'color'=>$field->getAttribute('data-g9-font-color'),
                ]])[$key];
                $size = $style['size'];
                $align = $field->getAttribute('data-g9-align');
                if (!in_array($align,['left','center','right'],true)) $align = 'center';
                $bold = $field->getAttribute('data-g9-bold')==='1' ? 'bold' : 'normal';
                $field->setAttribute('style', 'font-family:'.G9CertificateTypography::family($style['font'], true).';font-size:'.$size.'pt;color:'.$style['color'].$position.';text-align:'.$align.';font-weight:'.$bold);
                foreach ($field->childNodes as $line) {
                    if ($line instanceof DOMElement && str_contains($line->getAttribute('class'),'g9-certificate-line')) $line->setAttribute('style','width:'.(297*$width/100).'mm');
                }
                if ($key === 'title') self::curveTitle($document, $field, $style, $width, $bold === 'bold');
            }
            return $document->saveHTML();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function curveTitle(DOMDocument $document, DOMElement $field, array $style, float $width, bool $bold): void
    {
        $available = 297 * 72 / 25.4 * $width / 100;
        $lines = [];
        $widest = 0;
        foreach ($field->childNodes as $line) {
            if (!$line instanceof DOMElement || !str_contains($line->getAttribute('class'), 'g9-certificate-line')) continue;
            preg_match_all('/\X/u', $line->textContent, $matches);
            $widths = G9CertificateTitle::widths($matches[0], $style['font'], $style['size'], $bold || str_contains($line->getAttribute('class'), 'g9-line-bold'));
            $lines[] = [$line, $matches[0], $widths];
            $widest = max($widest, array_sum($widths));
        }
        $scale = $widest > 0 ? min(1, ($available - 3) / $widest) : 1;
        $size = $style['size'] * $scale;
        $field->setAttribute('style', $field->getAttribute('style').';font-size:'.$size.'pt');
        foreach ($lines as [$line, $characters, $widths]) {
            $curve = $field->getAttribute('data-g9-curve');
            $positions = G9CertificateTitle::arc(array_map(fn ($width) => $width * $scale, $widths), $available, $curve === '' ? 26 : (float) $curve);
            $height = $size * 1.2 + max([0, ...array_column($positions, 'top')]);
            $line->setAttribute('class', $line->getAttribute('class').' g9-title-arc');
            $line->setAttribute('role', 'img');
            $line->setAttribute('aria-label', $line->textContent);
            $line->setAttribute('style', $line->getAttribute('style').';height:'.$height.'pt');
            while ($line->firstChild) $line->removeChild($line->firstChild);
            foreach ($characters as $index => $character) {
                $position = $positions[$index] ?? ['left'=>0, 'top'=>0, 'rotation'=>0, 'width'=>0];
                $letter = $document->createElement('span');
                $letter->appendChild($document->createTextNode($character));
                $letter->setAttribute('class', 'g9-title-letter');
                $letter->setAttribute('aria-hidden', 'true');
                $letter->setAttribute('style', 'left:'.$position['left'].'pt;top:'.$position['top'].'pt;width:'.$position['width'].'pt;transform:rotate('.$position['rotation'].'deg)');
                $line->appendChild($letter);
            }
        }
    }
}
