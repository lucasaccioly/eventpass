<?php

namespace App\Support;

final class TicketCode
{
    /** No 0/O or 1/I, so a code read aloud or typed from paper can't be misread. */
    public const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public const LENGTH = 10;

    public static function generate(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }

    /** Typed codes may come lowercase, spaced or with dashes. */
    public static function normalize(string $input): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', strtoupper($input));
    }
}
