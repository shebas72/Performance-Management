<?php

namespace App\Services\Pdf;

use Illuminate\Support\HtmlString;

/**
 * Everything a PDF view needs besides its data: language and direction, translated labels, Arabic-safe text,
 * number formatting, status colours and the small CSS bars. Views get it as $p.
 *
 * Text rule: anything that can contain Arabic goes through x() / name() / t(), which shape it for DomPDF and escape it.
 * They return HtmlString, so Blade prints them with {{ }} and does not escape twice.
 */
final class PdfContext
{
    public const COLORS = ['on_track' => '#16a34a', 'at_risk' => '#d97706', 'behind' => '#dc2626', 'not_entered' => '#9ca3af'];
    public const SOFT   = ['on_track' => '#dcfce7', 'at_risk' => '#fef3c7', 'behind' => '#fee2e2', 'not_entered' => '#f3f4f6'];

    public readonly bool $rtl;
    public readonly string $dir;
    public readonly string $start;   // text-align for the start of a line
    public readonly string $end;

    private array $dict;

    public function __construct(public readonly string $lang, public readonly array $company, public readonly string $generatedAt, ?array $dict = null)
    {
        $this->rtl   = $lang === 'ar';
        $this->dir   = $this->rtl ? 'rtl' : 'ltr';
        $this->start = $this->rtl ? 'right' : 'left';
        $this->end   = $this->rtl ? 'left' : 'right';
        $this->dict  = $dict ?? (is_file($f = lang_path($lang.'/pdf.php')) ? require $f : []);
    }

    /** Translated label, shaped and escaped. Missing keys fall back to English, then to the key. */
    public function t(string $key, array $vars = []): HtmlString
    {
        return $this->x($this->raw($key, $vars));
    }

    /** Translated label as plain logical text (for building longer strings before shaping). */
    public function raw(string $key, array $vars = []): string
    {
        $s = data_get($this->dict, $key);
        if (! is_string($s) && $this->lang !== 'en' && is_file($f = lang_path('en/pdf.php'))) $s = data_get(require $f, $key);
        $s = is_string($s) ? $s : $key;
        foreach ($vars as $k => $v) $s = str_replace(':'.$k, (string) $v, $s);

        return $s;
    }

    /** Shape (Arabic only), escape, keep line breaks. $max wraps long Arabic text at that many characters. */
    public function x(?string $text, int $max = 0): HtmlString
    {
        // Arabic is shaped wherever it appears, so an Arabic name in an English report still draws correctly.
        $text = ArabicShaper::text((string) $text, $max);

        return new HtmlString(nl2br(e($text), false));
    }

    /** name_ar when the report is Arabic and it is filled, else name. Accepts an array or an object. */
    public function name($row, int $max = 0, string $field = 'name'): HtmlString
    {
        $en = data_get($row, $field);
        $ar = data_get($row, $field.'_ar');

        return $this->x($this->rtl && filled($ar) ? $ar : $en, $max);
    }

    public function num($v, int $dec = 1): string
    {
        return $v === null || $v === '' ? '—' : number_format((float) $v, $dec, '.', ',');
    }

    public function pct($v, int $dec = 1): string
    {
        return $v === null || $v === '' ? '—' : number_format((float) $v, $dec, '.', ',').'%';
    }

    public function color(?string $status): string
    {
        return self::COLORS[$status] ?? self::COLORS['not_entered'];
    }

    public function status(?string $status): HtmlString
    {
        return $this->t('status.'.($status ?: 'not_entered'));
    }

    /** A small coloured pill with the status label. */
    public function badge(?string $status): HtmlString
    {
        $s = $status && isset(self::COLORS[$status]) ? $status : 'not_entered';

        return new HtmlString('<span style="background:'.self::SOFT[$s].';color:'.self::COLORS[$s].';padding:1px 6px;font-size:8px;font-weight:bold;">'.$this->status($s).'</span>');
    }

    /** Horizontal bar for a score (full width = 100, or 120 when the score cap allows more). */
    public function bar($score, ?string $status, int $width = 70): HtmlString
    {
        $cap = max(100.0, (float) config('spms.score_cap', 120));
        $w = $score === null ? 0 : max(0, min(100, (float) $score / $cap * 100));
        $w = round($w, 1);

        // A nested table, not floats: DomPDF misplaces floats that sit in table cells next to each other.
        $fill = $w > 0 ? '<td style="width:'.$w.'%;height:7px;padding:0;border:0;background:'.$this->color($status).';"></td>' : '';
        $rest = $w < 100 ? '<td style="height:7px;padding:0;border:0;background:#e5e7eb;"></td>' : '';
        $cells = $this->rtl ? $rest.$fill : $fill.$rest;

        return new HtmlString('<table style="width:'.$width.'px;height:7px;border-collapse:collapse;'.($this->rtl ? 'margin-left:auto;margin-right:0;' : '').'"><tr>'.$cells.'</tr></table>');
    }

    /** Same bands as KpiCalculator::statusForScore (80 / 60), for scores that have no stored status. */
    public function scoreStatus($score): string
    {
        if ($score === null) return 'not_entered';

        return (float) $score >= 80 ? 'on_track' : ((float) $score >= 60 ? 'at_risk' : 'behind');
    }

    /** Percentage in bold with a bar under it. */
    public function scoreCell($score, ?string $status = null): HtmlString
    {
        $status ??= $this->scoreStatus($score);

        return new HtmlString('<b style="color:'.$this->color($status).';">'.e($this->pct($score)).'</b>'.($score === null ? '' : '<div style="height:3px;"></div>'.$this->bar($score, $status)));
    }

    public function month(int $m, bool $short = true): HtmlString
    {
        $key = ($short ? 'months_short.' : 'months.').$m;

        return $this->t($key);
    }

    public function period(int $year, int $month): HtmlString
    {
        return $this->x($this->raw('months.'.$month).' '.$year);
    }

    public function date(?string $iso): string
    {
        return $iso ? date('Y-m-d', strtotime($iso)) : '—';
    }

    /** Columns and cells are laid out right to left for Arabic. */
    public function order(array $items): array
    {
        return $this->rtl ? array_reverse($items) : $items;
    }

    /** 'start' | 'end' | 'center' -> a CSS text-align. */
    public function align(string $a): string
    {
        return match ($a) {
            'end' => $this->end, 'center' => 'center', default => $this->start,
        };
    }
}
