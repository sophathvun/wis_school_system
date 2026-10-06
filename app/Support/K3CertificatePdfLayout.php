<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Dompdf\Dompdf;

class K3CertificatePdfLayout
{
    public static function configure(Dompdf $pdf): void
    {
        TranscriptPdfLayout::configure($pdf,['k3-certificate-line']);
        $normalize = $pdf->getCallbacks()['begin_page_reflow'][0];
        $prepared = false;
        $pdf->setCallbacks([['event'=>'begin_page_reflow','f'=>static function($page,$canvas,$fonts) use (&$prepared,$normalize): void {
            if (!$prepared) {
                $prepared = true;
                foreach ($page->get_subtree() as $field) {
                    $node = $field->get_node();
                    if (!$node instanceof DOMElement || !$node->hasAttribute('data-k3-font-key')) continue;
                    $size = $field->get_style()->font_size;
                    $widest = 0; $available = 0;
                    foreach ($field->get_subtree() as $line) {
                        $lineNode = $line->get_node();
                        if (!$lineNode instanceof DOMElement || !str_contains($lineNode->getAttribute('class'),'k3-certificate-line')) continue;
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
            foreach ((new DOMXPath($document))->query('//*[@data-k3-layout-key]') as $field) {
                if (!$field instanceof DOMElement) continue;
                $key = $field->getAttribute('data-k3-layout-key');
                if ($key === 'qr') {
                    $width = max(8, min(20, (float) $field->getAttribute('data-k3-width')));
                    $x = max(0, min(100-$width, (float) $field->getAttribute('data-k3-x')));
                    $y = max(0, min(100-($width*297/100+3)*100/210, (float) $field->getAttribute('data-k3-y')));
                    $field->setAttribute('style', 'left:'.$x.'%;top:'.$y.'%;width:'.$width.'%');
                    foreach ($field->childNodes as $frame) {
                        if ($frame instanceof DOMElement && $frame->getAttribute('class') === 'k3-qr-frame') {
                            $frame->setAttribute('style', 'height:'.(297*$width/100).'mm');
                        }
                    }
                    continue;
                }
                $x = max(0,min(98,(float)$field->getAttribute('data-k3-x')));
                $y = max(0,min(95,(float)$field->getAttribute('data-k3-y')));
                $width = max($key==='photo'?2:5,min(100,(float)$field->getAttribute('data-k3-width')));
                $position = ';left:'.$x.'%;top:'.$y.'%;width:'.$width.'%';
                if ($key==='photo') {
                    $height = max(2,min(40,(float)$field->getAttribute('data-k3-height')));
                    $field->setAttribute('style',ltrim($position,';').';height:'.$height.'%');
                    continue;
                }
                if (!isset(K3CertificateTypography::fields()[$key])) continue;
                $style = K3CertificateTypography::resolve([$key=>[
                    'font'=>$field->getAttribute('data-k3-font-name'),
                    'size'=>$field->getAttribute('data-k3-font-size'),
                    'color'=>$field->getAttribute('data-k3-font-color'),
                ]])[$key];
                $size = $style['size'];
                $align = $field->getAttribute('data-k3-align');
                if (!in_array($align,['left','center','right'],true)) $align = 'center';
                $bold = $field->getAttribute('data-k3-bold')==='1' ? 'bold' : 'normal';
                $field->setAttribute('style', 'font-family:'.K3CertificateTypography::family($style['font'], true).';font-size:'.$size.'pt;color:'.$style['color'].$position.';text-align:'.$align.';font-weight:'.$bold);
                foreach ($field->childNodes as $line) {
                    if ($line instanceof DOMElement && str_contains($line->getAttribute('class'),'k3-certificate-line')) $line->setAttribute('style','width:'.(297*$width/100).'mm');
                }
            }
            return $document->saveHTML();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
