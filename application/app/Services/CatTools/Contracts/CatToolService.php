<?php

namespace App\Services\CatTools\Contracts;

use Illuminate\Http\Client\Response;

interface CatToolService
{
    /**
     * Method for setting up the translation in external CAT tool
     * @param string[] $filesIds
     * @return void
     */
    public function setupCatToolJobs(array $filesIds = null): void;

    /**
     * Returns response from XLIFF files download request
     * @return DownloadableFile
     */
    public function getDownloadableXLIFFsFile(): DownloadableFile;

    /**
     * Returns response from the translation files download request
     * @return DownloadableFile
     */
    public function getDownloadableTranslationsFile(): DownloadableFile;
}
