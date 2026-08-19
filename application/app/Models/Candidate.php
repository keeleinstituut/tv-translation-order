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
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * App\Models\Candidate
 *
 * @property string|null $id
 * @property string|null $assignment_id
 * @property string|null $vendor_id
 * @property string|null $status
 * @property Carbon|null $notified_at
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
 * @method static Builder|Candidate ordered($direction = 'asc') // from SortableTrait
 *
 * @mixin Eloquent
 */
class Candidate extends Model implements Sortable
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;
    use SortableTrait;

    protected $guarded = [];

    public $sortable = [
        'order_column_name' => 'position',
        'sort_when_creating' => true,
    ];

    protected $casts = [
        'status' => CandidateStatus::class,
        'position' => 'integer',
        'notified_at' => 'datetime',
    ];

    public function buildSortQuery(): Builder
    {
        return static::query()->where('assignment_id', $this->assignment_id);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }
}
