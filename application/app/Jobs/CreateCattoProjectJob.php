<?php

namespace App\Jobs;

use App\Models\SubProject;
use App\Services\CattoApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class CreateCattoProjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly SubProject $subProject)
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(CattoApiClient $cattoApiClient): void
    {
        if (filled($this->subProject->cat_metadata['catto_project_id'] ?? null)) {
            return;
        }

        $result = $cattoApiClient->createProject(
            $this->subProject->ext_id,
            $this->subProject->sourceLanguageClassifierValue->value,
            $this->subProject->project->institution_id,
        );

        $this->subProject->cat_metadata = array_merge(
            $this->subProject->cat_metadata ?? [],
            ['catto_project_id' => $result['id']],
        );
        $this->subProject->saveOrFail();
    }
}
