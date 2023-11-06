<?php

namespace App\Jobs;

use App\Enums\JobKey;
use App\Enums\SubProjectStatus;
use App\Models\Assignment;
use App\Models\Candidate;
use App\Models\JobDefinition;
use App\Models\SubProject;
use DB;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class TrackSubProjectStatus implements ShouldQueue, ShouldBeUnique
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
        //
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
            if (empty($jobDefinition)) {
                if ($this->subProject->status === SubProjectStatus::TasksInProgress) {
                    $this->subProject->status = SubProjectStatus::Completed;
                    $this->subProject->active_job_definition_id = null;
                    $this->subProject->saveOrFail();
                }

                return;
            }

            if (in_array($this->subProject->status, [SubProjectStatus::New, SubProjectStatus::Registered])) {
                $this->subProject->status = SubProjectStatus::TasksSubmittedToVendors;
                $this->subProject->active_job_definition_id = $jobDefinition->id;
                $this->subProject->saveOrFail();
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

            if ($this->subProject->status === SubProjectStatus::TasksSubmittedToVendors) {
                if ($this->hasAssigneeForAllAssignments($jobDefinition)) {
                    $this->subProject->status = SubProjectStatus::TasksInProgress;
                    $this->subProject->active_job_definition_id = $jobDefinition->id;
                    return;
                }
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
            }
        });
    }


    /**
     * TODO: implement calculation for JobDefinition based on the active Camunda tasks.
     *
     * @return JobDefinition|null
     */
    private function getWorkflowActiveJobDefinition(): ?JobDefinition
    {
        $service = $this->subProject->project->workflow();
        $tasks = collect($service->getTasks());

        // Iterate over the tasks and calculate active assignments job definition.
        return JobDefinition::query()->first();
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
