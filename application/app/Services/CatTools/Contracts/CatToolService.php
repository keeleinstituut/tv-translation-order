<?php

namespace App\Services\CatTools\Contracts;

use Illuminate\Http\Client\Response;

interface CatToolService
{
    /**
     * Method for setting up the translation in external CAT tool
     * @return void
     */
    public function setupJobs(): void;

    /**
     * Returns response from XLIFF files download request
     * @return Response
     */
    public function getXliffFileStreamedDownloadResponse(): Response;

    /**
     * Returns response from the translation files download request
     * @return Response
     */
    public function getTranslationFileStreamedDownloadResponse(): Response;
}
