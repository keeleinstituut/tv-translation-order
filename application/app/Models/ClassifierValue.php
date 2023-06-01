<?php

namespace App\Models;

use App\Enums\ClassifierValueType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use SyncTools\Traits\HasCachedEntityFactory;

class ClassifierValue extends Model
{
    use HasCachedEntityFactory, HasUuids, SoftDeletes;

    protected $table = 'entity_cache.cached_classifier_values';

    protected $casts = [
        'type' => ClassifierValueType::class,
        'meta' => 'array',
    ];
}
