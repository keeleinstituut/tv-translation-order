<?php

namespace App\Models;

use App\Models\CachedEntities\ClassifierValue;
use Database\Factories\ProjectTypeConfigFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * App\Models\ProjectTypeConfig
 *
 * @property string|null $id
 * @property string|null $type_classifier_value_id
 * @property string|null $workflow_process_definition_id
 * @property array|null $features
 * @property bool|null $is_start_date_supported
 * @property bool|null $cat_tool_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Collection<int, JobDefinition> $jobDefinitions
 * @property ClassifierValue $typeClassifierValue
 *
 * @method static ProjectTypeConfigFactory factory($count = null, $state = [])
 * @method static Builder|ProjectTypeConfig newModelQuery()
 * @method static Builder|ProjectTypeConfig newQuery()
 * @method static Builder|ProjectTypeConfig query()
 * @method static Builder|ProjectTypeConfig whereCreatedAt($value)
 * @method static Builder|ProjectTypeConfig whereFeatures($value)
 * @method static Builder|ProjectTypeConfig whereId($value)
 * @method static Builder|ProjectTypeConfig whereTypeClassifierValueId($value)
 * @method static Builder|ProjectTypeConfig whereUpdatedAt($value)
 * @method static Builder|ProjectTypeConfig whereWorkflowProcessDefinitionId($value)
 * @method static Builder|ProjectTypeConfig whereIsStartDateSupported($value)
 *
 * @property-read int|null $job_definitions_count
 *
 * @method static Builder|ProjectTypeConfig whereCatToolEnabled($value)
 *
 * @mixin Eloquent
 */
class ProjectTypeConfig extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    protected $table = 'project_type_configs';

    protected $casts = [
        'features' => 'array',
    ];

    public function jobDefinitions(): HasMany
    {
        return $this->hasMany(JobDefinition::class);
    }

    public function typeClassifierValue(): HasOne
    {
        return $this->hasOne(ClassifierValue::class, 'id', 'type_classifier_value_id');
    }
}
