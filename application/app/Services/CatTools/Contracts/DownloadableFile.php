<?php

namespace App\Services\CatTools\Contracts;

interface DownloadableFile
{
    public function getName(): string;

    public function getContent(): mixed;
}
