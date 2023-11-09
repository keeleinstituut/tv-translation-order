<?php

namespace App\Jobs;

use App\Enums\JobKey;
use App\Enums\SubProjectStatus;
use App\Models\Assignment;
use App\Models\Candidate;
use App\Models\JobDefinition;
use App\Models\SubProject;
use DB;
use DomainException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class TrackSubProjectStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly SubProject $subProject)
    {
    }

    /**
     * Execute the job.
     * @throws Throwable
     */
    public function handle(): void
    {
        if ($this->subProject->status === SubProjectStatus::Cancelled) {
            return;
        }

        if ($this->subProject->status === SubProjectStatus::Completed) {
            return;
        }

        $jobDefinition = $this->getWorkflowActiveJobDefinition();

        /** Empty job definition means that there are no tasks that have relation with assignments */
        DB::transaction(function () use ($jobDefinition) {
            if (empty($jobDefinition) && $this->subProject->workflow_started) {
                $this->subProject->status = SubProjectStatus::Completed;
                $this->subProject->active_job_definition_id = null;
                $this->subProject->saveOrFail();

                TrackProjectStatus::dispatch($this->subProject->project);
                return;
            }

            if (in_array($this->subProject->status, [SubProjectStatus::New, SubProjectStatus::Registered])) {
                $this->subProject->status = SubProjectStatus::TasksSubmittedToVendors;
                $this->subProject->active_job_definition_id = $jobDefinition->id;
                $this->subProject->saveOrFail();
                return;
            }

            if ($this->subProject->status === SubProjectStatus::TasksSubmittedToVendors) {
                if ($this->hasAssigneeForAllAssignments($jobDefinition)) {
                    $this->subProject->status = SubProjectStatus::TasksInProgress;
                }

                $this->subProject->active_job_definition_id = $jobDefinition->id;
                $this->subProject->saveOrFail();
                return;
            }

            if ($this->subProject->status === SubProjectStatus::TasksInProgress) {
                if ($this->subProject->active_job_definition_id !== $jobDefinition->id) {
                    if ($this->hasAssignmentWithoutCandidates($jobDefinition)) {
                        $this->subProject->status = SubProjectStatus::TasksCompleted;
                    } elseif ($this->hasAssignmentWithoutAssignee($jobDefinition)) {
                        $this->subProject->status = SubProjectStatus::TasksSubmittedToVendors;
                        $this->subProject->active_job_definition_id = $jobDefinition->id;
                    } else {
                        $this->subProject->status = SubProjectStatus::TasksInProgress;
                        $this->subProject->active_job_definition_id = $jobDefinition->id;
                    }

                    $this->subProject->saveOrFail();
                }
                return;
            }

            if ($this->subProject->status === SubProjectStatus::TasksCompleted) {
                if ($this->hasAssignmentWithoutCandidates($jobDefinition)) {
                    return;
                }

                $this->subProject->status = $this->hasAssignmentWithoutAssignee($jobDefinition) ?
                    SubProjectStatus::TasksSubmittedToVendors :
                    SubProjectStatus::TasksInProgress;

                $this->subProject->active_job_definition_id = $jobDefinition->id;
                $this->subProject->saveOrFail();
            }
        });
    }


    /**
     * @return JobDefinition|null
     */
    private function getWorkflowActiveJobDefinition(): ?JobDefinition
    {
        $searchResults = $this->subProject->project->workflow()->getTasksSearchResult([
            'processVariables' => [
                [
                    'name' => 'sub_project_id',
                    'value' => $this->subProject->id,
                    'operator' => 'eq'
                ]
            ]
        ]);

        $assignments = $searchResults->getTasks()->pluck('assignment')->filter();
        if (empty($assignments)) {
            return null;
        }

        $jobDefinitionsIds = $assignments->pluck('job_definition_id')->unique();
        if ($jobDefinitionsIds->count() > 1) {
            throw new DomainException('Current state of the workflow contains multiple job definitions');
        }

        return JobDefinition::query()->find($jobDefinitionsIds->get(0));
    }

    private function hasAssignmentWithoutCandidates(JobDefinition $jobDefinition): bool
    {
        /** For overview, we don't need candidates as the work will be done by PM */
        if ($jobDefinition->job_key === JobKey::JOB_OVERVIEW) {
            return false;
        }

        return $this->subProject->assignments()
            ->where('job_definition_id', $jobDefinition->id)
            ->whereDoesntHave('candidates')
            ->exists();
    }

    private function hasAssignmentWithoutAssignee(JobDefinition $jobDefinition): bool
    {
        return $this->subProject->assignments()
            ->where('job_definition_id', $jobDefinition->id)
            ->whereNull('assigned_vendor_id')
            ->exists();
    }

    private function hasAssigneeForAllAssignments(JobDefinition $jobDefinition): bool
    {
        return $this->subProject->assignments()
            ->where('job_definition_id', $jobDefinition->id)
            ->whereNull('assigned_vendor_id')
            ->exists();
    }
}
