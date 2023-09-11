<?php

namespace App\Services\CatTools\Contracts;

use App\Models\CatToolJob;
use App\Models\Media;
use App\Services\CatTools\Exceptions\CatToolSetupFailedException;
use Illuminate\Database\Eloquent\Collection;

interface CatToolService
{
    /**
     * Method for setting up the translation in external CAT tool
     * @param string[] $filesIds
     * @return void
     * @throws CatToolSetupFailedException
     */
    public function setupJobs(array $filesIds = null): void;

    /**
     * @param int $jobsCount
     * @return Collection<int, CatToolJob>
     */
    public function split(int $jobsCount): Collection;

    /**
     * @return Collection<int, CatToolJob>
     */
    public function merge(): Collection;

    /**
     * @return Collection<int, Media>
     */
    public function getSourceFiles(): Collection;

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

    /**
     * @return bool
     */
    public function isAnalyzed(): bool;

    /**
     * @return bool
     * @throws CatToolSetupFailedException
     */
    public function isCreated(): bool;
}
