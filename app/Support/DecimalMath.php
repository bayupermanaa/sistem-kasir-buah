<?php

namespace App\Support;

use InvalidArgumentException;

final class DecimalMath
{
    public static function parse(mixed $value, int $scale): int
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException('Nilai desimal wajib diisi.');
        }

        $text = is_float($value) ? sprintf('%.' . $scale . 'f', $value) : trim((string) $value);
        $pattern = '/^(-?)(?:0|[1-9]\d*)(?:\.(\d{1,' . $scale . '}))?$/';
        if (! preg_match($pattern, $text, $matches)) {
            throw new InvalidArgumentException('Format desimal tidak valid.');
        }

        $unsigned = ltrim($matches[0], '-');
        $whole = (int) explode('.', $unsigned, 2)[0];
        $fraction = $matches[2] ?? '';
        $scaled = ($whole * (10 ** $scale)) + (int) str_pad($fraction, $scale, '0');

        return ($matches[1] ?? '') === '-' ? -$scaled : $scaled;
    }

    public static function format(int $value, int $scale): string
    {
        $negative = $value < 0;
        $value = abs($value);
        $base = 10 ** $scale;

        return ($negative ? '-' : '') . intdiv($value, $base) . '.' . str_pad((string) ($value % $base), $scale, '0', STR_PAD_LEFT);
    }

    public static function multiply(int $left, int $right, int $divisor): int
    {
        $product = $left * $right;
        $negative = $product < 0;
        $product = abs($product);
        $result = intdiv($product + intdiv($divisor, 2), $divisor);

        return $negative ? -$result : $result;
    }
}
