<?php

namespace App\Services\CatTools\Contracts;

use Illuminate\Support\Collection;

interface SplittableCatTools extends CatToolService
{
    public function split(int $chunksCount): void;

    public function merge(Collection $xliffFile): void;
}
