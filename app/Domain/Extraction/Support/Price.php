<?php

namespace App\Domain\Extraction\Support;

/**
 * Exact decimal price handling (bcmath strings, never floats).
 */
final class Price
{
    /** "45000" with minor unit 2 → "450.00". */
    public static function fromMinor(int|string|null $amount, int $minorUnit): ?string
    {
        if ($amount === null || $amount === '' || ! preg_match('/^-?\d+$/', (string) $amount)) {
            return null;
        }
        $minorUnit = max(0, min(8, $minorUnit));

        return bcdiv((string) $amount, bcpow('10', (string) $minorUnit), $minorUnit);
    }

    /**
     * Parse a human or schema.org price: "1,234.50", "1.234,50", "€ 49,00", "450", 450.0 → "1234.50" etc.
     * When only one separator occurs it is treated as decimal if followed by 1–2 digits at the end.
     */
    public static function parse(int|float|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.') ?: '0';
        }

        $s = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = preg_replace('/[^\d.,\-]/u', '', $s) ?? '';
        if ($s === '' || ! preg_match('/\d/', $s)) {
            return null;
        }

        $lastDot = strrpos($s, '.');
        $lastComma = strrpos($s, ',');
        if ($lastDot !== false && $lastComma !== false) {
            $decimal = $lastDot > $lastComma ? '.' : ',';
        } elseif ($lastDot !== false || $lastComma !== false) {
            $sep = $lastDot !== false ? '.' : ',';
            $pos = $lastDot !== false ? $lastDot : $lastComma;
            $after = strlen($s) - $pos - 1;
            $decimal = (substr_count($s, $sep) === 1 && $after > 0 && $after <= 2) ? $sep : null;
        } else {
            $decimal = null;
        }

        if ($decimal === null) {
            $normalized = str_replace(['.', ','], '', $s);
        } else {
            $thousands = $decimal === '.' ? ',' : '.';
            $normalized = str_replace([$thousands, $decimal], ['', '.'], $s);
        }

        return preg_match('/^-?\d+(\.\d+)?$/', $normalized) ? $normalized : null;
    }

    /** True when $a < $b (both decimal strings). */
    public static function lessThan(?string $a, ?string $b): bool
    {
        if ($a === null || $b === null) {
            return false;
        }

        return bccomp($a, $b, 8) < 0;
    }
}
