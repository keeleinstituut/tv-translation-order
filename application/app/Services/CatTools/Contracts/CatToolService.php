<?php

namespace App\Services\CatTools\Contracts;

use App\Services\CatTools\CatAnalysisResult;
use App\Services\CatTools\CatToolUserTask;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;

interface CatToolService
{
    /**
     * Method for setting up the translation in external CAT tool
     * @return void
     */
    public function createProject(): void;

    /**
     * Returns list of meta about user tasks
     * @return Collection<int, CatToolUserTask>
     */
    public function getUserTasks(): Collection;

    /**
     * Returns list of analysis results
     * @return Collection<int, CatAnalysisResult>
     */
    public function getAnalysisResults(): Collection;

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
