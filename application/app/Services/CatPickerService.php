<?php

namespace App\Services;



use App\Services\CatTools\MateCat\MateCatService;
use RuntimeException;

class CatPickerService
{
    public const MATECAT = 'matecat';

    public static function pick($catName): string
    {
        return match ($catName) {
            static::MATECAT => MateCatService::class,
            default => throw new RuntimeException("No CAT service with name \"$catName\" exists"),
        };
    }
}
