<?php

namespace App\Services\CatTools;

use App\Services\CatTools\Contracts\DownloadableFile;

readonly class VolumeAnalysisReportFile implements DownloadableFile
{
    public function __construct(private string $content, private string $name)
    {

    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContent(): mixed
    {
        return $this->content;
    }
}
