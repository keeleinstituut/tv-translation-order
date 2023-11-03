<?php

namespace App\Models;

use App\Enums\JobKey;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Models\JobDefinition
 *
 * @property int $id
 * @property string $project_type_config_id
 * @property JobKey $job_key
 * @property string $skill_id
 * @property bool $multi_assignments_enabled
 * @property bool $linking_with_cat_tool_jobs_enabled
 * @property int $sequence
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property ProjectTypeConfig $projectTypeConfig
 *
 * @method static Builder|JobDefinition newModelQuery()
 * @method static Builder|JobDefinition newQuery()
 * @method static Builder|JobDefinition query()
 * @method static Builder|JobDefinition whereId($value)
 * @method static Builder|JobDefinition whereJobKey($value)
 * @method static Builder|JobDefinition whereMultiAssignmentsEnabled($value)
 * @method static Builder|JobDefinition whereProjectTypeConfigId($value)
 * @method static Builder|JobDefinition whereSkillId($value)
 * @method static Builder|JobDefinition whereCreatedAt($value)
 * @method static Builder|JobDefinition whereDeletedAt($value)
 * @method static Builder|JobDefinition whereUpdatedAt($value)
 * @method static Builder|JobDefinition onlyTrashed()
 * @method static Builder|JobDefinition whereLinkingWithCatToolJobsEnabled($value)
 * @method static Builder|JobDefinition whereSequence($value)
 * @method static Builder|JobDefinition withTrashed()
 * @method static Builder|JobDefinition withoutTrashed()
 *
 * @mixin Eloquent
 */
class JobDefinition extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $casts = [
        'job_key' => JobKey::class,
    ];

    protected $fillable = [
        'project_type_config_id',
        'job_key',
        'multi_assignments_enabled',
        'linking_with_cat_tool_jobs_enabled',
        'sequence',
    ];

    public function projectTypeConfig(): BelongsTo
    {
        return $this->belongsTo(ProjectTypeConfig::class);
    }
}
