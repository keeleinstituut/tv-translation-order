<?php

namespace App\Models\CachedEntities;

use App\Enums\ClassifierValueType;
use App\Models\ProjectTypeConfig;
use Database\Factories\CachedEntities\ClassifierValueFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use SyncTools\Traits\HasCachedEntityFactory;
use SyncTools\Traits\IsCachedEntity;

/**
 * App\Models\CachedEntities\ClassifierValue
 *
 * @property string|null $id
 * @property ClassifierValueType|null $type
 * @property string|null $value
 * @property string|null $name
 * @property array|null $meta
 * @property string|null $synced_at
 * @property Carbon|null $deleted_at
 * @property-read ProjectTypeConfig|null $projectTypeConfig
 *
 * @method static ClassifierValueFactory factory($count = null, $state = [])
 * @method static Builder|ClassifierValue newModelQuery()
 * @method static Builder|ClassifierValue newQuery()
 * @method static Builder|ClassifierValue onlyTrashed()
 * @method static Builder|ClassifierValue query()
 * @method static Builder|ClassifierValue whereDeletedAt($value)
 * @method static Builder|ClassifierValue whereId($value)
 * @method static Builder|ClassifierValue whereMeta($value)
 * @method static Builder|ClassifierValue whereName($value)
 * @method static Builder|ClassifierValue whereSyncedAt($value)
 * @method static Builder|ClassifierValue whereType($value)
 * @method static Builder|ClassifierValue whereValue($value)
 * @method static Builder|ClassifierValue withTrashed()
 * @method static Builder|ClassifierValue withoutTrashed()
 *
 * @mixin Eloquent
 */
class ClassifierValue extends Model
{
    use IsCachedEntity, HasCachedEntityFactory, HasUuids, SoftDeletes;

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
