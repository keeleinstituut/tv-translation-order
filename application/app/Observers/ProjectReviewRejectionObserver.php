<?php

namespace App\Observers;

use App\Models\Project;
use App\Models\ProjectReviewRejection;
use Str;

class ProjectReviewRejectionObserver
{
    public function creating(ProjectReviewRejection $projectReviewRejection): void
    {
        $projectReviewRejection->id = Str::orderedUuid();
        $projectReviewRejection->file_collection = join('/', [
            Project::REVIEW_FILES_COLLECTION_PREFIX,
            $projectReviewRejection->id
        ]);
    }

    /**
     * Handle the ProjectReviewRejection "created" event.
     */
    public function created(ProjectReviewRejection $projectReviewRejection): void
    {
        //
    }

    /**
     * Handle the ProjectReviewRejection "updated" event.
     */
    public function updated(ProjectReviewRejection $projectReviewRejection): void
    {
        //
    }

    /**
     * Handle the ProjectReviewRejection "deleted" event.
     */
    public function deleted(ProjectReviewRejection $projectReviewRejection): void
    {
        //
    }

    /**
     * Handle the ProjectReviewRejection "restored" event.
     */
    public function restored(ProjectReviewRejection $projectReviewRejection): void
    {
        //
    }

    /**
     * Handle the ProjectReviewRejection "force deleted" event.
     */
    public function forceDeleted(ProjectReviewRejection $projectReviewRejection): void
    {
        //
    }
}
