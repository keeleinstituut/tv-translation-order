<?php

namespace App\Observers;

use App\Enums\ProjectStatus;
use App\Enums\SubProjectStatus;
use App\Jobs\Workflows\UpdateProjectClientInsideWorkflow;
use App\Jobs\Workflows\UpdateProjectDeadlineInsideWorkflow;
use App\Jobs\Workflows\UpdateProjectManagerInsideWorkflow;
use App\Models\Project;
use App\Models\Sequence;
use App\Models\SubProject;
use Illuminate\Http\Client\RequestException;
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
                data_get($project->typeClassifierValue->meta, 'code', ''),
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

        if ($project->isDirty('status')) {
            if ($project->status === ProjectStatus::Cancelled) {
                $project->cancelled_at = Carbon::now();

                $project->subProjects->each(function (SubProject $subProject) {
                    $subProject->status = SubProjectStatus::Cancelled;
                    $subProject->saveOrFail();
                });
            } elseif ($project->status === ProjectStatus::Accepted) {
                $project->accepted_at = Carbon::now();
            } elseif ($project->status === ProjectStatus::Corrected) {
                $project->corrected_at = Carbon::now();
            } elseif ($project->status === ProjectStatus::Rejected) {
                $project->rejected_at = Carbon::now();
            } elseif ($project->status === ProjectStatus::SubmittedToClient) {
                $project->submitted_to_client_review_at = Carbon::now();
            }
        }
    }

    /**
     * Handle the Project "updated" event.
     * @throws RequestException
     * @throws Throwable
     */
    public function updated(Project $project): void
    {
        if ($project->wasChanged('manager_institution_user_id')) {
            UpdateProjectManagerInsideWorkflow::dispatch($project);
        }

        if ($project->wasChanged('client_institution_user_id')) {
            UpdateProjectClientInsideWorkflow::dispatch($project);
        }

        if ($project->wasChanged('deadline_at') && filled($project->deadline_at)) {
            $project->subProjects->each(function (SubProject $subProject) use ($project) {
                if (empty($subProject->deadline_at) || $subProject->deadline_at > $project->deadline_at) {
                    $subProject->deadline_at = $project->deadline_at;
                    $subProject->saveOrFail();
                }
            });
            UpdateProjectDeadlineInsideWorkflow::dispatchSync($project);
        }

        if ($project->wasChanged('event_start_at')) {
            $project->subProjects->each(function (SubProject $subProject) use ($project) {
                $subProject->event_start_at = $project->event_start_at;
                if (filled($project->event_start_at) && filled($subProject->deadline_at) && $subProject->deadline_at < $project->event_start_at) {
                    $subProject->event_start_at = null;
                }

                $subProject->saveOrFail();
            });
        }
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
