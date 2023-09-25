<?php

namespace App\Observers;

use App\Models\AssignmentCatToolJob;
use App\Models\Volume;

class AssignmentCatToolJobObserver
{
    /**
     * Handle the AssignmentCatToolJob "created" event.
     */
    public function created(AssignmentCatToolJob $assignmentCatToolJob): void
    {
        //
    }

    /**
     * Handle the AssignmentCatToolJob "updated" event.
     */
    public function updated(AssignmentCatToolJob $assignmentCatToolJob): void
    {
        //
    }

    /**
     * Handle the AssignmentCatToolJob "deleted" event.
     */
    public function deleted(AssignmentCatToolJob $assignmentCatToolJob): void
    {
        Volume::where('assignment_id', $assignmentCatToolJob->assignment_id)
            ->where('cat_tool_job_id', $assignmentCatToolJob->cat_tool_job_id)
            ->each(fn (Volume $volume) => $volume->delete());
    }
}
