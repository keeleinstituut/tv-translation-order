<?php

namespace App\Services\CatTools\Contracts;

interface SplittableCatTools extends CatToolService
{
    public function split(int $chunksCount): void;
    public function merge(): void;
}
