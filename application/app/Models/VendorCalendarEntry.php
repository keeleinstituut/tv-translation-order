<?php

namespace App\Models;

use App\Enums\VendorCalendarEntryType;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $vendor_id
 * @property Carbon $start_at
 * @property Carbon $end_at
 * @property string|null $assignment_id
 * @property string|null $prebook_institution_user_id
 * @property Carbon|null $prebook_at
 * @property array<array-key, mixed>|null $metadata
 * @property string|null $vendor_calendar_import_id
 * @property string|null $institution_user_vacation_id
 * @property string|null $institution_vacation_id
 * @property string|null $absence_creator_institution_user_id
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \App\Models\Assignment|null $assignment
 * @property-read VendorCalendarEntryType $type
 * @property-read \App\Models\VendorCalendarImport|null $import
 * @property-read \App\Models\Vendor $vendor
 * @method static Builder<static>|VendorCalendarEntry absencesOnly()
 * @method static Builder<static>|VendorCalendarEntry assignmentsOnly()
 * @method static Builder<static>|VendorCalendarEntry vacationsOnly()
 * @method static Builder<static>|VendorCalendarEntry forClient(string $institutionUserId)
 * @method static Builder<static>|VendorCalendarEntry withoutPrebooked()
 * @method static Builder<static>|VendorCalendarEntry newModelQuery()
 * @method static Builder<static>|VendorCalendarEntry newQuery()
 * @method static Builder<static>|VendorCalendarEntry onlyTrashed()
 * @method static Builder<static>|VendorCalendarEntry overlapping(\Illuminate\Support\Carbon $from, \Illuminate\Support\Carbon $to)
 * @method static Builder<static>|VendorCalendarEntry query()
 * @method static Builder<static>|VendorCalendarEntry whereAssignmentId($value)
 * @method static Builder<static>|VendorCalendarEntry whereCreatedAt($value)
 * @method static Builder<static>|VendorCalendarEntry whereDeletedAt($value)
 * @method static Builder<static>|VendorCalendarEntry whereEndAt($value)
 * @method static Builder<static>|VendorCalendarEntry whereId($value)
 * @method static Builder<static>|VendorCalendarEntry whereMetadata($value)
 * @method static Builder<static>|VendorCalendarEntry wherePrebookAt($value)
 * @method static Builder<static>|VendorCalendarEntry wherePrebookInstitutionUserId($value)
 * @method static Builder<static>|VendorCalendarEntry whereStartAt($value)
 * @method static Builder<static>|VendorCalendarEntry whereUpdatedAt($value)
 * @method static Builder<static>|VendorCalendarEntry whereVendorCalendarImportId($value)
 * @method static Builder<static>|VendorCalendarEntry whereVendorId($value)
 * @method static Builder<static>|VendorCalendarEntry withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|VendorCalendarEntry withoutTrashed()
 * @mixin Eloquent
 */
class VendorCalendarEntry extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'vendor_calendar_entries';

    protected $guarded = [];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'prebook_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** Derived from nullable FK state — no extra column needed. */
    public function getTypeAttribute(): VendorCalendarEntryType
    {
        return match (true) {
            ! is_null($this->assignment_id) => VendorCalendarEntryType::Assignment,
            ! is_null($this->prebook_institution_user_id) => VendorCalendarEntryType::Prebook,
            ! is_null($this->vendor_calendar_import_id) => VendorCalendarEntryType::ExternalCalendar,
            ! is_null($this->absence_creator_institution_user_id) => VendorCalendarEntryType::Absence,
            default => VendorCalendarEntryType::Vacation,
        };
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(VendorCalendarImport::class, 'vendor_calendar_import_id');
    }

    /** Core availability primitive — overlapping time interval check. */
    public function scopeOverlapping(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->where('start_at', '<', $to)
            ->where('end_at', '>', $from)
            ->whereNull('deleted_at');
    }

    /** Only absence-type entries (TPM-created vendor out-of-office). */
    public function scopeAbsencesOnly(Builder $query): Builder
    {
        return $query->whereNotNull('absence_creator_institution_user_id');
    }

    /** Only assignment-type bookings. */
    public function scopeAssignmentsOnly(Builder $query): Builder
    {
        return $query->whereNotNull('assignment_id');
    }

    /** Only vacation-type entries (have a vacation source ID set). */
    public function scopeVacationsOnly(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNotNull('institution_user_vacation_id')
            ->orWhereNotNull('institution_vacation_id')
        );
    }

    /** Only bookings belonging to a specific client user's orders. */
    public function scopeForClient(Builder $query, string $institutionUserId): Builder
    {
        return $query->whereHas(
            'assignment.subProject.project',
            fn (Builder $sub) => $sub->where('client_institution_user_id', $institutionUserId)
        );
    }

    public function scopeWithoutPrebooked(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('prebook_institution_user_id')
            ->whereNull('prebook_at')
        );
    }
}
