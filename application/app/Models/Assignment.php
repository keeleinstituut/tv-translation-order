<?php

namespace App\Models;

use App\Services\Prices\AssigneePriceCalculator;
use App\Services\Prices\PriceCalculator;
use AuditLogClient\Enums\AuditLogEventObjectType;
use AuditLogClient\Models\AuditLoggable;
use Database\Factories\AssignmentFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * App\Models\Assignment
 *
 * @property string|null $id
 * @property string|null $sub_project_id
 * @property string|null $job_definition_id
 * @property string|null $assigned_vendor_id
 * @property string|null $ext_id
 * @property string|null $deadline_at
 * @property string|null $comments
 * @property string|null $assignee_comments
 * @property string|null $feature
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Vendor|null $assignee
 * @property-read Collection<int, Candidate> $candidates
 * @property-read int|null $candidates_count
 * @property-read SubProject $subProject
 * @property-read JobDefinition $jobDefinition
 * @property-read Collection<int, Volume> $volumes
 * @property-read int|null $volumes_count
 * @property-read Collection<int, CatToolJob> $catToolJobs
 *
 * @method static AssignmentFactory factory($count = null, $state = [])
 * @method static Builder|Assignment newModelQuery()
 * @method static Builder|Assignment newQuery()
 * @method static Builder|Assignment query()
 * @method static Builder|Assignment whereAssignedVendorId($value)
 * @method static Builder|Assignment whereAssigneeComments($value)
 * @method static Builder|Assignment whereComments($value)
 * @method static Builder|Assignment whereCreatedAt($value)
 * @method static Builder|Assignment whereDeadlineAt($value)
 * @method static Builder|Assignment whereFeature($value)
 * @method static Builder|Assignment whereId($value)
 * @method static Builder|Assignment whereSubProjectId($value)
 * @method static Builder|Assignment whereUpdatedAt($value)
 *
 * @property-read int|null $cat_tool_jobs_count
 *
 * @method static Builder|Assignment whereExtId($value)
 * @method static Builder|Assignment whereJobDefinitionId($value)
 *
 * @mixin Eloquent
 */
class Assignment extends Model implements AuditLoggable
{
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    public function subProject(): BelongsTo
    {
        return $this->belongsTo(SubProject::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'assigned_vendor_id');
    }

    public function volumes(): HasMany
    {
        return $this->hasMany(Volume::class, 'assignment_id');
    }

    public function catToolJobs(): BelongsToMany
    {
        return $this->belongsToMany(CatToolJob::class, AssignmentCatToolJob::class)
            ->using(AssignmentCatToolJob::class);
    }

    public function jobDefinition(): BelongsTo
    {
        return $this->belongsTo(JobDefinition::class);
    }

    public function getPriceCalculator(): PriceCalculator
    {
        return new AssigneePriceCalculator($this);
    }

    public function getIdentitySubset(): array
    {
        return $this->only(['id', 'ext_id']);
    }

    public function getAuditLogRepresentation(): array
    {
        return $this->withoutRelations()
            ->refresh()
            ->load([
                'assignee',
                'candidates',
                'subProject',
                'jobDefinition',
                'volumes',
                'volumes.catToolJob',
                'catToolJobs',
            ])
            ->toArray();
    }

    public function getAuditLogObjectType(): AuditLogEventObjectType
    {
        return AuditLogEventObjectType::Assignment;
    }
}
