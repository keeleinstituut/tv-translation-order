<?php

namespace App\Enums;

enum TagType: string
{
    case TranslationMemory = 'Tõlkemälud';
    case Vendor = 'Teostaja';
    case Order = 'Tellimus';
    case VendorSkill = 'Oskused';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
