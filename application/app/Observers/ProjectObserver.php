<?php

namespace App\Observers;

use App\Models\Project;
use App\Models\Sequence;
use Illuminate\Support\Carbon;
use Throwable;

class ProjectObserver
{
    /**
     * Handle the Project "creating" event.
     */
    public function creating(Project $project): void
    {
        if ($project->ext_id == null) {
            $project->ext_id = collect([
                $project->institution->short_name,
                Carbon::now()->format('Y-m'),
                $project->typeClassifierValue->meta['code'] ?? '',
                $project->institution->institutionProjectSequence->incrementCurrentValue(),
            ])->implode('-');
        }
    }

    /**
     * Handle the Project "created" event.
     *
     * @throws Throwable
     */
    public function created(Project $project): void
    {
        $seq = new Sequence();
        $seq->sequenceable_id = $project->id;
        $seq->sequenceable_type = Project::class;
        $seq->name = Sequence::PROJECT_SUBPROJECT_SEQ;
        $seq->saveOrFail();
    }

    /**
     * Handle the Project "updated" event.
     */
    public function updated(Project $project): void
    {
        //
    }

    /**
     * Handle the Project "deleted" event.
     */
    public function deleted(Project $project): void
    {
        //
    }

    /**
     * Handle the Project "restored" event.
     */
    public function restored(Project $project): void
    {
        //
    }

    /**
     * Handle the Project "force deleted" event.
     */
    public function forceDeleted(Project $project): void
    {
        //
    }
}
