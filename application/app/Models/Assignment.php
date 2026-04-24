<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Enums\ExternalRequestRecipientStatus;
use App\Enums\ExternalRequestStatus;
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
use App\Models\CachedEntities\Institution;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * App\Models\Assignment
 *
 * @property string|null $id
 * @property string|null $sub_project_id
 * @property string|null $job_definition_id
 * @property string|null $assigned_vendor_id
 * @property string|null $ext_id
 * @property AssignmentStatus $status
 * @property float|null $price
 * @property Carbon|null $deadline_at
 * @property string|null $comments
 * @property string|null $assignee_comments
 * @property string|null $feature
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $event_start_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $timeslot_passed_notification_sent_at
 * @property string|null $external_institution_id
 * @property-read Vendor|null $assignee
 * @property-read Collection<int, Candidate> $candidates
 * @property-read int|null $candidates_count
 * @property-read SubProject $subProject
 * @property-read JobDefinition $jobDefinition
 * @property-read Collection<int, Volume> $volumes
 * @property-read int|null $volumes_count
 * @property-read Collection<int, CatToolJob> $catToolJobs
 * @property-read VendorCalendarEntry|null $calendarEntry
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
 * @mixin Eloquent
 */
class Assignment extends Model implements AuditLoggable
{
    use HasUuids;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'status' => AssignmentStatus::class,
        'deadline_at' => 'datetime',
        'event_start_at' => 'datetime',
        'completed_at' => 'datetime',
        'price' => 'float',
        'timeslot_passed_notification_sent_at' => 'datetime',
    ];

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

    public function calendarEntry(): HasOne
    {
        return $this->hasOne(VendorCalendarEntry::class);
    }

    public function jobDefinition(): BelongsTo
    {
        return $this->belongsTo(JobDefinition::class);
    }

    public function scopeSharedWithInstitution(Builder $query, ?string $institutionId): void
    {
        if (empty($institutionId)) {
            return;
        }

        $query->where(function (Builder $sharedQuery) use ($institutionId) {
            $sharedQuery->where('external_institution_id', $institutionId)
                ->orWhereHas('externalTranslationRequests.recipients', function (Builder $q) use ($institutionId) {
                    $q->where('institution_id', $institutionId)
                        ->whereIn('status', ExternalRequestRecipientStatus::activeForPartner())
                        ->whereHas('externalTranslationRequest',
                            fn (Builder $requestQuery) => $requestQuery->where('status', ExternalRequestStatus::Active));
                });
        });
    }

    public function externalTranslationRequests(): HasMany
    {
        return $this->hasMany(ExternalTranslationRequest::class);
    }

    public function externalInstitution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'external_institution_id');
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

    public function getSameJobDefinitionAssignmentsQuery(): Builder
    {
        return Assignment::where('job_definition_id', $this->job_definition_id)
            ->where('sub_project_id', $this->sub_project_id)
            ->whereNot('id', $this->id);
    }
}
