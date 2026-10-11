<?php

namespace App\Services\Pdf;

/**
 * Arabic text for DomPDF, which has no Arabic shaping and no bidi: it draws each code point left to right in isolated form.
 * This turns a logical string into what must be drawn: letters swapped for their joined presentation forms (U+FE70 block),
 * runs put in visual order, Latin words and numbers kept left to right. Text without Arabic is returned untouched.
 *
 * Wrapping has to happen before reordering (a reordered line cannot be wrapped), so long text is wrapped here by character
 * count and returned as lines; the caller joins them with <br>.
 *
 * Scope: Arabic letters, Persian/Urdu letters folded to their Arabic base, digits, Latin text and common punctuation.
 * Diacritics (tashkeel) and tatweel are dropped, which is what you want in a business report.
 */
final class ArabicShaper
{
    /** base letter => [isolated, final, initial, medial]; 0 = the letter has no such form. */
    private const FORMS = [
        0x0621 => [0xFE80, 0, 0, 0],
        0x0622 => [0xFE81, 0xFE82, 0, 0],
        0x0623 => [0xFE83, 0xFE84, 0, 0],
        0x0624 => [0xFE85, 0xFE86, 0, 0],
        0x0625 => [0xFE87, 0xFE88, 0, 0],
        0x0626 => [0xFE89, 0xFE8A, 0xFE8B, 0xFE8C],
        0x0627 => [0xFE8D, 0xFE8E, 0, 0],
        0x0628 => [0xFE8F, 0xFE90, 0xFE91, 0xFE92],
        0x0629 => [0xFE93, 0xFE94, 0, 0],
        0x062A => [0xFE95, 0xFE96, 0xFE97, 0xFE98],
        0x062B => [0xFE99, 0xFE9A, 0xFE9B, 0xFE9C],
        0x062C => [0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0],
        0x062D => [0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4],
        0x062E => [0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8],
        0x062F => [0xFEA9, 0xFEAA, 0, 0],
        0x0630 => [0xFEAB, 0xFEAC, 0, 0],
        0x0631 => [0xFEAD, 0xFEAE, 0, 0],
        0x0632 => [0xFEAF, 0xFEB0, 0, 0],
        0x0633 => [0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4],
        0x0634 => [0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8],
        0x0635 => [0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC],
        0x0636 => [0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0],
        0x0637 => [0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4],
        0x0638 => [0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8],
        0x0639 => [0xFEC9, 0xFECA, 0xFECB, 0xFECC],
        0x063A => [0xFECD, 0xFECE, 0xFECF, 0xFED0],
        0x0641 => [0xFED1, 0xFED2, 0xFED3, 0xFED4],
        0x0642 => [0xFED5, 0xFED6, 0xFED7, 0xFED8],
        0x0643 => [0xFED9, 0xFEDA, 0xFEDB, 0xFEDC],
        0x0644 => [0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0],
        0x0645 => [0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4],
        0x0646 => [0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8],
        0x0647 => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC],
        0x0648 => [0xFEED, 0xFEEE, 0, 0],
        0x0649 => [0xFEEF, 0xFEF0, 0, 0],
        0x064A => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4],
    ];

    /** Lam + alef ligatures: alef variant => [isolated, final]. */
    private const LIGATURES = [
        0x0622 => [0xFEF5, 0xFEF6],
        0x0623 => [0xFEF7, 0xFEF8],
        0x0625 => [0xFEF9, 0xFEFA],
        0x0627 => [0xFEFB, 0xFEFC],
    ];

    /** Persian / Urdu letters mapped to the Arabic letter with the nearest shape. */
    private const FOLD = [0x067E => 0x0628, 0x0686 => 0x062C, 0x0698 => 0x0632, 0x06A9 => 0x0643, 0x06AF => 0x0643, 0x06CC => 0x064A, 0x06C0 => 0x0647, 0x06BE => 0x0647];

    private const MIRROR = ['(' => ')', ')' => '(', '[' => ']', ']' => '[', '{' => '}', '}' => '{', '<' => '>', '>' => '<'];

    public static function hasArabic(?string $s): bool
    {
        return $s !== null && $s !== '' && preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $s) === 1;
    }

    /** Display-ready text, one string per line. $maxChars = 0 means no wrapping. */
    public static function lines(?string $text, int $maxChars = 0): array
    {
        $text = trim((string) $text);
        if ($text === '') return [''];
        if (! self::hasArabic($text)) return [$text];

        $text = self::clean($text);
        $out = [];
        foreach (preg_split('/\R/u', $text) as $para) {
            foreach (self::wrap($para, $maxChars) as $line) {
                $out[] = self::visual($line);
            }
        }

        return $out ?: [''];
    }

    /** Same as lines(), joined with "\n". */
    public static function text(?string $text, int $maxChars = 0): string
    {
        return implode("\n", self::lines($text, $maxChars));
    }

    private static function clean(string $s): string
    {
        // Arabic-Indic and Persian digits -> Latin digits; drop tashkeel, superscript alef, tatweel.
        $s = strtr($s, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٪' => '%', '،' => ',', '؛' => ';', '؟' => '?',
        ]);

        return preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $s);
    }

    private static function wrap(string $s, int $max): array
    {
        if ($max <= 0 || mb_strlen($s) <= $max) return [$s];

        $lines = [];
        $cur = '';
        foreach (preg_split('/\s+/u', $s, -1, PREG_SPLIT_NO_EMPTY) as $w) {
            if ($cur !== '' && mb_strlen($cur) + 1 + mb_strlen($w) > $max) {
                $lines[] = $cur;
                $cur = $w;
            } else {
                $cur = $cur === '' ? $w : $cur.' '.$w;
            }
        }
        if ($cur !== '') $lines[] = $cur;

        return $lines;
    }

    /** One logical line -> shaped and in visual order (right-to-left paragraph). */
    private static function visual(string $line): string
    {
        $cps = array_map('mb_ord', mb_str_split($line));
        $cps = array_map(fn ($c) => self::FOLD[$c] ?? $c, $cps);

        // 1. shape
        $n = count($cps);
        $shaped = [];
        for ($i = 0; $i < $n; $i++) {
            $c = $cps[$i];
            if (! isset(self::FORMS[$c])) { $shaped[] = $c; continue; }

            $prevJoins = $i > 0 && isset(self::FORMS[$cps[$i - 1]]) && self::FORMS[$cps[$i - 1]][2] !== 0;

            if ($c === 0x0644 && $i + 1 < $n && isset(self::LIGATURES[$cps[$i + 1]])) {
                $lig = self::LIGATURES[$cps[$i + 1]];
                $shaped[] = $prevJoins ? $lig[1] : $lig[0];
                $i++;
                // After a ligature the next letter starts a new word (alef does not join forward).
                $cps[$i] = 0x0627;
                continue;
            }

            $nextJoins = self::FORMS[$c][2] !== 0 && $i + 1 < $n && isset(self::FORMS[$cps[$i + 1]]);
            $f = self::FORMS[$c];
            if ($prevJoins && $nextJoins)      $g = $f[3] ?: $f[1];
            elseif ($prevJoins)                $g = $f[1];
            elseif ($nextJoins)                $g = $f[2] ?: $f[0];
            else                               $g = $f[0];
            $shaped[] = $g;
        }

        // 2. classify: R (Arabic), L (Latin / digits / number punctuation), N (neutral)
        $type = [];
        $m = count($shaped);
        $isDigit = fn ($c) => $c >= 0x30 && $c <= 0x39;
        for ($i = 0; $i < $m; $i++) {
            $c = $shaped[$i];
            if (($c >= 0x600 && $c <= 0x6FF) || ($c >= 0xFB50 && $c <= 0xFDFF) || ($c >= 0xFE70 && $c <= 0xFEFF)) { $type[$i] = 'R'; continue; }
            if ($isDigit($c) || ($c < 0x250 && ctype_alpha(chr($c < 256 ? $c : 0))) || ($c >= 0xC0 && $c < 0x250)) { $type[$i] = 'L'; continue; }
            // . , : / - + % between or after digits stay part of the number (12.5, 1,200, 10/12, 5%)
            $prevD = $i > 0 && $isDigit($shaped[$i - 1]);
            $nextD = $i + 1 < $m && $isDigit($shaped[$i + 1]);
            if (in_array($c, [0x2E, 0x2C, 0x3A, 0x2F, 0x2D], true) && $prevD && $nextD) { $type[$i] = 'L'; continue; }
            if ($c === 0x25 && $prevD) { $type[$i] = 'L'; continue; }
            $type[$i] = 'N';
        }

        // Matching brackets take the direction of what they enclose: "(YTD)" stays left to right, "(مؤشر)" is mirrored.
        $open = [0x28 => 0x29, 0x5B => 0x5D, 0x7B => 0x7D];
        $stack = [];
        for ($i = 0; $i < $m; $i++) {
            if (isset($open[$shaped[$i]])) { $stack[] = $i; continue; }
            if (in_array($shaped[$i], $open, true) && $stack) {
                $o = array_pop($stack);
                $inner = array_slice($type, $o + 1, $i - $o - 1);
                $dir = in_array('R', $inner, true) ? 'R' : (in_array('L', $inner, true) ? 'L' : 'R');
                $type[$o] = $type[$i] = $dir;
            }
        }

        // 3. neutrals take the direction of their neighbours when both agree, else the paragraph direction (R)
        for ($i = 0; $i < $m; $i++) {
            if ($type[$i] !== 'N') continue;
            $j = $i; while ($j < $m && $type[$j] === 'N') $j++;
            $left  = $i > 0 ? $type[$i - 1] : 'R';
            $right = $j < $m ? $type[$j] : 'R';
            $dir   = $left === $right ? $left : 'R';
            for ($k = $i; $k < $j; $k++) $type[$k] = $dir;
            $i = $j - 1;
        }

        // 4. runs -> reverse run order; reverse characters inside R runs (mirroring brackets); keep L runs as they are
        $runs = [];
        for ($i = 0; $i < $m; ) {
            $j = $i; while ($j < $m && $type[$j] === $type[$i]) $j++;
            $runs[] = [$type[$i], array_slice($shaped, $i, $j - $i)];
            $i = $j;
        }
        $out = '';
        foreach (array_reverse($runs) as [$d, $chars]) {
            if ($d === 'R') $chars = array_reverse($chars);
            foreach ($chars as $c) {
                $ch = mb_chr($c);
                $out .= $d === 'R' ? (self::MIRROR[$ch] ?? $ch) : $ch;
            }
        }

        return $out;
    }
}
