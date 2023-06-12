<?php

namespace App\Services\CAT;

use App\Enums\Feature;
use App\Jobs\MateCatCheckProjectStatusJob;
use App\Models\SubProject;
use Illuminate\Support\Str;


// Needs refactoring
// should be responsible for communicating with MateCAT,
// providing generalized information from CAT to be used in unified way by other parts of the code.
// Uses $order->cat_metadata field as a internal storage for all cat related stuff
class MateCatService
{
    private SubProject $subProject;

    public const ANALYSIS_STATUS_DONE = 'DONE';

    private const RESPONSE_CREATE_PROJECT = 'response_create_project';
    private const RESPONSE_STATUS = 'response_status';
    private const RESPONSE_URLS = 'response_urls';

    public function __construct(SubProject $subProject) {
        $this->subProject = $subProject;
    }

    public function createProject()
    {
        $result = MateCatServiceBase::createProject([
            'files' => $this->subProject->sourceFiles,
            'project_name' => $this->subProject->id,
            'source_lang' => $this->subProject->sourceLanguageClassifierValue->meta['iso3_code'],
            'target_lang' => $this->subProject->destinationLanguageClassifierValue->meta['iso3_code'],
        ]);

        $this->setToStorage(self::RESPONSE_CREATE_PROJECT, $result);
        $this->subProject->save();

        MateCatCheckProjectStatusJob::dispatch($this->subProject);
        return $result;
    }

    public function propagateUrls()
    {
        $result = MateCatServiceBase::urls($this->getProjectId(), $this->getProjectPass());
        $this->setToStorage(self::RESPONSE_URLS, $result);
        return $result;
    }

    public function propagateStatus()
    {
        $result = MateCatServiceBase::status($this->getProjectId(), $this->getProjectPass());
        $this->setToStorage(self::RESPONSE_STATUS, $result);
        return $result;
    }

    public function getProjectId()
    {
        return $this->getFromStorage(self::RESPONSE_CREATE_PROJECT . ".id_project");
    }

    public function getProjectPass()
    {
        return $this->getFromStorage(self::RESPONSE_CREATE_PROJECT . ".project_pass");
    }

    public function getAnalyzisStatus() {
        return $this->getFromStorage(self::RESPONSE_STATUS . ".status");
    }

    public function getJobs() {
        $jobs = collect($this->getFromStorage(self::RESPONSE_URLS . ".urls.jobs"));
        if ($jobs->isEmpty()) {
            return null;
        }

        return $jobs
            ->reduce(function ($acc, $job) {
                collect($job['chunks'])->each(function ($chunk) use ($acc, $job) {
                    $chunk['job'] = $job;
                    $acc->push($chunk);
                });
                return $acc;
            }, collect())
            ->map(function ($chunk) {
                return new MateCatJobsResource($chunk);
            })
            ->pipe(function ($collection) {
                return json_decode($collection->toJson(), true);
            });
//        return collect($jobs)->pluck('chunks')->collapse()->map(function ($chunk) {
//            return new MateCatJobsResource($chunk);
//        });
    }

    public function getFiles()
    {
        return $this->getFromStorage(self::RESPONSE_URLS . ".urls.files");
    }

    private function getStorage()
    {
        return $this->subProject->cat_metadata;
    }

    private function getFromStorage(string $key, $fallback = null)
    {
        $storage = $this->getStorage();
        return data_get($storage, $key, $fallback);
    }

    private function setToStorage($key, $value, $overwrite = true)
    {
        $storage = $this->getStorage();
        return data_set($storage, $key, $value, $overwrite);
    }

    public function getAnalyzis2(){
        $jobs = $this->getFromStorage(self::RESPONSE_STATUS . ".data.jobs");
        return collect($jobs)->pluck('totals');
    }

    public function getAnalyzis()
    {
        $jobs = $this->getFromStorage(self::RESPONSE_STATUS . ".data.jobs");
        return collect($jobs)->pluck('totals')->reduce(function ($acc, $value) {
            collect($value)->each(function ($chunk, $chunkId) use ($acc) {
                $values = [
                    'tm_101' => data_get($chunk, 'ICE.0'),
                    'tm_repetitions' => data_get($chunk, 'REPETITIONS.0'),
                    'tm_100' => data_get($chunk, 'TM_100.0'),
                    'tm_95_99' => data_get($chunk, 'TM_95_99.0'),
                    'tm_85_94' => data_get($chunk, 'TM_85_94.0'),
                    'tm_75_84' => data_get($chunk, 'TM_75_84.0'),
                    'tm_50_74' => data_get($chunk, 'TM_50_74.0'),
                    'tm_0_49' => data_get($chunk, 'TM_0_49.0'),
                ];

                $acc->push([
                    'chunk_id' => $chunkId,
                    'total' => collect($values)->values()->sum(),
                    'raw_word_count' => data_get($chunk, 'raw_word_count.0'),
                    ...$values,
                ]);
            });
            return $acc;
        }, collect());
    }

    public function tempGetUrls()
    {
        return $this->getFromStorage(self::RESPONSE_URLS);
    }

    public static function getSupportedFeatures($prefix = '') {
        return collect([
            Feature::JOB_TRANSLATION,
            Feature::JOB_REVISION,
        ])->filter(function ($elem) use ($prefix) {
            if ($prefix) {
                return Str::startsWith($elem->value, $prefix);
            }
            return true;
        });
    }
}
