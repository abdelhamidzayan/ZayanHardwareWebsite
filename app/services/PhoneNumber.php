<?php
declare(strict_types=1);

namespace App\Services;

final class PhoneNumber
{
    public static function fromParts(string $dial, string $input): ?string
    {
        $dial = preg_replace('/\D+/', '', $dial) ?? '';
        if ($dial === '') return null;

        $input = strtr($input, [
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        ]);
        $raw = trim($input);
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') return null;

        $international = str_starts_with($raw, '+') || str_starts_with($digits, '00');
        if (str_starts_with($digits, '00')) $digits = substr($digits, 2);
        if ($international) {
            $digits = ltrim($digits, '0');
            if (str_starts_with($digits, $dial)) $digits = substr($digits, strlen($dial));
        }

        $digits = ltrim($digits, '0');
        if (str_starts_with($digits, $dial)) {
            $withoutDial = substr($digits, strlen($dial));
            if (strlen($withoutDial) >= 7) $digits = $withoutDial;
        }

        if ($dial === '20') {
            if (preg_match('/^1[0125]\d{8}$/', $digits) !== 1) return null;
        } elseif (preg_match('/^\d{6,12}$/', $digits) !== 1) {
            return null;
        }

        return '+' . $dial . $digits;
    }

    public static function guess(string $input, array $dials): ?string
    {
        $input = strtr(trim($input), [
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        ]);
        if ($input === '') return null;
        $digits = preg_replace('/\D+/', '', $input) ?? '';
        if ($digits === '') return null;

        if (str_starts_with($input, '+') || str_starts_with($digits, '00')) {
            if (str_starts_with($digits, '00')) $digits = substr($digits, 2);
            $codes = array_values(array_unique(array_map(static fn ($dial): string => preg_replace('/\D+/', '', (string) $dial) ?? '', $dials)));
            usort($codes, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
            foreach ($codes as $code) {
                if ($code !== '' && str_starts_with($digits, $code)) {
                    return self::fromParts($code, '+' . $digits);
                }
            }
            return null;
        }

        return self::fromParts('20', $input);
    }
}
