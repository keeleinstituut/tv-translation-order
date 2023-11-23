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
     * Handle the Media "deleted" event.
     */
    public function deleted(Media $media): void
    {
        if ($media->hasCustomProperty('copy_media_id')) {
            if (filled($copiedMedia = Media::find($media->getCustomProperty('copy_media_id')))) {
                $copiedMedia->delete();
            }
        }
    }
}
