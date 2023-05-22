<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\MorphPivot;

class Taggable extends MorphPivot
{
    use HasUuids;

    protected $table = 'taggables';
}
