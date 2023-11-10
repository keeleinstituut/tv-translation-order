<?php

namespace App\Observers;

use App\Enums\ProjectStatus;
use App\Enums\SubProjectStatus;
use App\Models\Project;
use App\Models\Sequence;
use App\Models\SubProject;
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
     * @throws Throwable
     */
    public function updating(Project $project): void
    {
//        if ($project->isDirty('manager_institution_user_id')) {
//            $project->workflow()->isStarted() && $project->workflow()
//                ->updateProcessInstanceVariable(
//                    'manager_institution_user_id',
//                    $project->manager_institution_user_id
//                );
//        }

//        if ($project->isDirty('client_institution_user_id')) {
//            $project->workflow()->isStarted() && $project->workflow()
//                ->updateProcessInstanceVariable(
//                    'client_institution_user_id',
//                    $project->client_institution_user_id
//                );
//        }

        $newProjectGotManager = $project->status === ProjectStatus::New &&
            $project->isDirty('manager_institution_user_id') &&
            is_null($project->getOriginal('manager_institution_user_id'));

        if ($newProjectGotManager) {
            $project->status = ProjectStatus::Registered;
            $project->subProjects->each(function (SubProject $subProject) {
                $subProject->status = SubProjectStatus::Registered;
                $subProject->saveOrFail();
            });
        }

        $projectWasCancelled = $project->status === ProjectStatus::Cancelled &&
            $project->isDirty('status');

        if ($projectWasCancelled) {
            $project->subProjects->each(function (SubProject $subProject) {
                $subProject->status = SubProjectStatus::Cancelled;
                $subProject->saveOrFail();
            });
        }
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
