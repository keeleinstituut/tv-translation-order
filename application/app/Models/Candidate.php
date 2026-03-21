<?php

namespace App\Models;

use App\Enums\CandidateStatus;
use Database\Factories\CandidateFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Models\Candidate
 *
 * @property string|null $id
 * @property string|null $assignment_id
 * @property string|null $vendor_id
 * @property string|null $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Assignment|null $assignment
 * @property-read Vendor|null $vendor
 *
 * @method static CandidateFactory factory($count = null, $state = [])
 * @method static Builder|Candidate newModelQuery()
 * @method static Builder|Candidate newQuery()
 * @method static Builder|Candidate onlyTrashed()
 * @method static Builder|Candidate query()
 * @method static Builder|Candidate withTrashed()
 * @method static Builder|Candidate withoutTrashed()
 * @method static Builder|Candidate whereAssignmentId($value)
 * @method static Builder|Candidate whereCreatedAt($value)
 * @method static Builder|Candidate whereId($value)
 * @method static Builder|Candidate whereUpdatedAt($value)
 * @method static Builder|Candidate whereVendorId($value)
 *
 * @mixin Eloquent
 */
class Candidate extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::addGlobalScope('ordered', fn (Builder $query) => $query->orderBy('position'));
    }

    protected $casts = [
        'status' => CandidateStatus::class,
        'position' => 'integer',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }
}
