<?php

declare(strict_types=1);

namespace App\Support;

final class Normalizer
{
    public static function digitsOnly(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }

    public static function zipcode(string $cep): string
    {
        return self::digitsOnly($cep);
    }

    public static function stateAbbr(string $abbr): string
    {
        return strtoupper(trim($abbr));
    }

    public static function text(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    public static function citySlug(string $name): string
    {
        $slug = mb_strtolower(trim($name), 'UTF-8');
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
        if ($transliterated !== false) {
            $slug = $transliterated;
        }
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'unknown';
    }

    public static function maskedZipcode(string $digits): string
    {
        if (strlen($digits) !== 8) {
            return $digits;
        }

        return substr($digits, 0, 5) . '-' . substr($digits, 5);
    }
}
