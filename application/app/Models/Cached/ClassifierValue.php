<?php

namespace App\Models\Cached;

use App\Enums\ClassifierValueType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use SyncTools\Traits\HasCachedEntityDbSchema;
use SyncTools\Traits\HasCachedEntityFactory;

class ClassifierValue extends Model
{
    use HasCachedEntityFactory, HasCachedEntityDbSchema, HasUuids, SoftDeletes;

    protected $table = 'cached_classifier_values';

    protected $casts = [
        'type' => ClassifierValueType::class,
        'meta' => 'array',
    ];
}
