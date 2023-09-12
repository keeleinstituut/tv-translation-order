<?php

namespace App\Services\CatTools\MateCat;

use App\Services\CatTools\Contracts\DownloadableFile;
use Illuminate\Http\Client\Response;
use Symfony\Component\Mime\MimeTypes;

readonly class MateCatDownloadableFile implements DownloadableFile
{
    public function __construct(private Response $response, private string $name)
    {
    }

    public function getName(): string
    {
        return "{$this->name}-".time().'.'.$this->getFileExtension();
    }

    public function getContent(): mixed
    {
        return $this->response->toPsrResponse()->getBody()->getContents();
    }

    private function getFileExtension(): string
    {
        return (new MimeTypes)->getExtensions(
            $this->response->header('content-type')
        )[0];
    }
}
