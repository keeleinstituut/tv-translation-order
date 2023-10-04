<?php

namespace App\Services;

use App\Services\CAT\MateCatService;

class CatPickerService
{
    public const MATECAT = 'matecat';

    /**
     * @throws \Exception
     */
    public static function pick($catName): string
    {
        return match ($catName) {
            static::MATECAT => MateCatService::class,
            default => throw new \Exception("No CAT service with name \"{$catName}\" exists", 1),
        };
    }
}
