<?php

namespace App\Jobs\Workflows;

use App\Enums\JobKey;
use App\Enums\SubProjectStatus;
use App\Models\JobDefinition;
use App\Models\SubProject;
use App\Services\Workflows\Tasks\TasksSearchResult;
use DB;
use DomainException;
use Illuminate\Bus\Queueable;
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

        $tasksSearchResult = $this->subProject->workflow()->getTasksSearchResult();
        $jobDefinition = $tasksSearchResult->getActiveJobDefinition();
        DB::transaction(function () use ($jobDefinition, $tasksSearchResult) {
            /** Empty job definition means that there are no tasks that have relation with assignments */
            if (empty($jobDefinition) && $this->subProject->workflow()->isStarted()) {
                $this->subProject->status = SubProjectStatus::Completed;
                $this->subProject->active_job_definition_id = null;
                $this->subProject->saveOrFail();

                TrackProjectStatus::dispatchSync($this->subProject->project);
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
        return !$this->subProject->assignments()
            ->where('job_definition_id', $jobDefinition->id)
            ->whereNull('assigned_vendor_id')
            ->exists();
    }
}
