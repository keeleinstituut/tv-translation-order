<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * App\Models\AssignmentCatToolJob
 *
 * @property string $id
 * @property string $assignment_id
 * @property string $cat_tool_job_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Assignment $assignment
 * @property-read CatToolJob $catToolJob
 *
 * @method static Builder|AssignmentCatToolJob newModelQuery()
 * @method static Builder|AssignmentCatToolJob newQuery()
 * @method static Builder|AssignmentCatToolJob query()
 * @method static Builder|AssignmentCatToolJob whereAssignmentId($value)
 * @method static Builder|AssignmentCatToolJob whereCatToolJobId($value)
 * @method static Builder|AssignmentCatToolJob whereCreatedAt($value)
 * @method static Builder|AssignmentCatToolJob whereId($value)
 * @method static Builder|AssignmentCatToolJob whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class AssignmentCatToolJob extends Pivot
{
    use HasFactory, HasUuids;

    protected $table = 'assignment_cat_tool_jobs';

    protected $fillable = [
        'cat_tool_job_id',
        'assignment_id',
    ];

    public function catToolJob(): BelongsTo
    {
        return $this->belongsTo(CatToolJob::class, 'cat_tool_job_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class, 'assignment_id');
    }
}
