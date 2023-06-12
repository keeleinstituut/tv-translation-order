<?php

namespace App\Models;

use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

class Media extends BaseMedia
{
    public function getContent($conversionName = '')
    {
        return Storage::disk($this->disk)->get($this->getUrl($conversionName));
    }
}
