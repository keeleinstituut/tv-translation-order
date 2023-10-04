<?php

namespace App\Enums;

enum VolumeUnits: string
{
    case Characters = 'CHARACTERS';
    case Words = 'WORDS';
    case Pages = 'PAGES';
    case Minutes = 'MINUTES';
    case Hours = 'HOURS';

    public static function values(): array
    {
        return array_column(VolumeUnits::cases(), 'value');
    }
}
