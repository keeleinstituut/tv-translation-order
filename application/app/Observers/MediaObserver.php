<?php

namespace App\Observers;

use App\Models\Media;

class MediaObserver
{
    /**
     * Handle the Media "created" event.
     */
    public function created(Media $media): void
    {
        //
    }

    /**
     * Handle the Media "updated" event.
     */
    public function updated(Media $media): void
    {
        //
    }

    /**
     * Handle the Media "deleting" event.
     */
    public function deleting(Media $media): void
    {
        if ($media->copies->isNotEmpty()) {
            $media->copies->each(fn (Media $media) => $media->delete());
        }
    }
}
