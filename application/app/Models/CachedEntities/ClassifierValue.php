<?php

namespace App\Models\CachedEntities;

use App\Enums\ClassifierValueType;
use App\Models\ProjectTypeConfig;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use SyncTools\Traits\IsCachedEntity;

class ClassifierValue extends Model
{
    use IsCachedEntity, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'cached_classifier_values';

    public $timestamps = false;

    protected $casts = [
        'type' => ClassifierValueType::class,
        'meta' => 'array',
    ];

    public function projectTypeConfig()
    {
        return $this->hasOne(ProjectTypeConfig::class, 'type_classifier_value_id');
    }
}
