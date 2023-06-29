<?php

namespace App\CatTools;

use App\CatTools\MateCat\Contracts\SourceFile;
use RuntimeException;

readonly class LocalFile implements SourceFile
{
    public function __construct(private string $filePath)
    {
        if (! file_exists($this->filePath)) {
            throw new RuntimeException("File doesn't exist");
        }
    }

    public function getName(): string
    {
        return basename($this->filePath);
    }

    public function getContent(): string
    {
        return file_get_contents($this->filePath);
    }
}
