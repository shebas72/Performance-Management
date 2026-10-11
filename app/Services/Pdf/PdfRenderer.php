<?php

namespace App\Services\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;

/** Renders a pdf.* Blade view to PDF bytes with DomPDF, adding the page footer (company, page x of y). */
class PdfRenderer
{
    public function render(string $view, array $data, PdfContext $p, string $orientation = 'portrait'): string
    {
        return $this->renderHtml(View::make($view, $data + ['p' => $p])->render(), $p, $orientation);
    }

    public function renderHtml(string $html, PdfContext $p, string $orientation = 'portrait'): string
    {
        $dir = storage_path('app/dompdf');
        if (! is_dir($dir)) @mkdir($dir, 0775, true);

        $o = new Options();
        $o->set('defaultFont', 'DejaVu Sans');       // ships with DomPDF and has the Arabic presentation forms
        $o->set('isFontSubsettingEnabled', true);
        $o->set('isRemoteEnabled', false);           // nothing in a report needs to fetch a URL
        $o->set('isPhpEnabled', false);
        $o->set('tempDir', $dir);
        $o->set('fontCache', $dir);
        $o->set('chroot', [base_path(), storage_path()]);
        $o->set('dpi', 96);

        $dompdf = new Dompdf($o);
        $dompdf->setPaper('A4', $orientation);
        $dompdf->loadHtml($html);
        $dompdf->render();

        $this->footer($dompdf, $p);

        return $dompdf->output();
    }

    private function footer(Dompdf $dompdf, PdfContext $p): void
    {
        $canvas = $dompdf->getCanvas();
        $font   = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $w = $canvas->get_width();
        $h = $canvas->get_height();
        $size = 7.5;
        $gray = [0.42, 0.45, 0.5];
        $y = $h - 26;

        $canvas->line(36, $y - 6, $w - 36, $y - 6, [0.88, 0.9, 0.92], 0.6);

        $name = ArabicShaper::text($p->company['name'] ?? '');
        $gen  = $p->rtl ? $p->raw('generated').' '.$p->generatedAt : $p->raw('generated').' '.$p->generatedAt;
        $gen  = ArabicShaper::text($gen);
        // Page x of y, in reading order for the report's language.
        $page = $p->rtl
            ? '{PAGE_COUNT} '.ArabicShaper::text($p->raw('page_of')).' {PAGE_NUM} '.ArabicShaper::text($p->raw('page'))
            : $p->raw('page').' {PAGE_NUM} '.$p->raw('page_of').' {PAGE_COUNT}';

        $left  = $p->rtl ? $page : $name.'   |   '.$gen;
        $right = $p->rtl ? $name.'   |   '.$gen : $page;

        $canvas->page_text(36, $y, $left, $font, $size, $gray);
        $textWidth = $dompdf->getFontMetrics()->getTextWidth(preg_replace('/\{PAGE_(NUM|COUNT)\}/', '00', $right), $font, $size);
        $canvas->page_text($w - 36 - $textWidth, $y, $right, $font, $size, $gray);
    }
}
