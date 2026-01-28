<?php

namespace App\Services\CatTools\Contracts;

use App\Models\CatToolJob;
use App\Models\Media;
use App\Services\CatTools\Enums\CatToolAnalyzingStatus;
use App\Services\CatTools\Enums\CatToolSetupStatus;
use App\Services\CatTools\Exceptions\CatToolSetupFailedException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\RequestException;

interface CatToolService
{
    /**
     * Method for setting up the translation in external CAT tool
     *
     * @param  string[]  $filesIds
     *
     * @throws CatToolSetupFailedException
     */
    public function setupJobs(?array $filesIds = null): void;

    /**
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
     */
    public function getDownloadableXLIFFsFile(): DownloadableFile;

    /**
     * Returns response from the translation files download request
     */
    public function getDownloadableTranslationsFile(): DownloadableFile;

    public function getAnalyzingStatus(): CatToolAnalyzingStatus;

    public function getSetupStatus(): CatToolSetupStatus;

    public function toggleMtEngine(bool $isEnabled): void;

    public function hasMtEnabled(): bool;

    /**
     * @throws RequestException
     */
    public function setTmKeys(): void;
}
