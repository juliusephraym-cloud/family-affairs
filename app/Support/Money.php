<?php

namespace App\Support;

/** Whole Tanzanian shillings. */
final class Money
{
    public static function format(?int $amount, bool $withCurrency = true): string
    {
        if ($amount === null) {
            return '—';
        }
        $s = number_format(abs($amount), 0, '.', ',');
        $s = ($amount < 0 ? '−' : '').$s;

        return $withCurrency ? 'TSh '.$s : $s;
    }

    /** Accepts "20,000", "20 000", "20000/=", "TSh 20,000". */
    public static function parse(mixed $input): ?int
    {
        if (is_int($input)) {
            return $input;
        }
        $digits = preg_replace('/[^\d]/', '', preg_replace('/\/=\s*$/', '', (string) $input) ?? '');

        return $digits === '' ? null : (int) $digits;
    }
}
