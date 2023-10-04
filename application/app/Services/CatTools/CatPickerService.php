<?php

namespace App\Services\CatTools;

use App\Models\SubProject;
use App\Services\CatTools\Contracts\CatToolService;
use App\Services\CatTools\MateCat\MateCat;
use RuntimeException;

readonly class CatPickerService
{
    public const MATECAT = 'matecat';

    public function __construct(private SubProject $subProject)
    {
    }

    public function pick($catName): CatToolService
    {
        return match ($catName) {
            static::MATECAT => new MateCat($this->subProject),
            default => throw new RuntimeException("No CAT service with name \"$catName\" exists"),
        };
    }
}
