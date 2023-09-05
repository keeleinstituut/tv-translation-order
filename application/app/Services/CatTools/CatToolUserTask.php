<?php

namespace App\Services\CatTools;

readonly class CatToolUserTask
{
    public function __construct(
        public string $id,
        public int    $progressPercentage,
        public string $translateUrl,
        public string $reviseUrl,
        public string $xliffDownloadUrl,
        public string $translationDownloadUrl,
        public array $meta
    )
    {
    }
}
