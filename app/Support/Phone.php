<?php

namespace App\Support;

/**
 * Phone normalisation to E.164 digits WITHOUT the plus (NextSMS expects
 * "255712345678"). Tanzanian local forms are accepted: 0712 345 678,
 * 712345678, +255 712 345 678, 255712345678. Family abroad may use any
 * other country code as long as it is written with a leading + or 00.
 */
final class Phone
{
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }
        $raw = trim($input);
        $international = str_starts_with($raw, '+') || str_starts_with($raw, '00');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (str_starts_with($raw, '00')) {
            $digits = substr($digits, 2);
        }

        // Tanzanian forms
        if (preg_match('/^0([67]\d{8})$/', $digits, $m)) {
            return '255'.$m[1];
        }
        if (preg_match('/^([67]\d{8})$/', $digits, $m) && ! $international) {
            return '255'.$m[1];
        }
        if (str_starts_with($digits, '255')) {
            return preg_match('/^255[67]\d{8}$/', $digits) ? $digits : null;
        }

        // Other countries: must have been written internationally
        if ($international && preg_match('/^[1-9]\d{7,14}$/', $digits)) {
            return $digits;
        }

        return null;
    }

    public static function display(?string $e164): string
    {
        if (! $e164) {
            return '—';
        }
        if (preg_match('/^255(\d{3})(\d{3})(\d{3})$/', $e164, $m)) {
            return "+255 {$m[1]} {$m[2]} {$m[3]}";
        }

        return '+'.$e164;
    }

    /** For logs: never write a full number into a log line. */
    public static function mask(?string $e164): string
    {
        return $e164 ? substr($e164, 0, 5).str_repeat('•', max(0, strlen($e164) - 8)).substr($e164, -3) : '—';
    }
}
