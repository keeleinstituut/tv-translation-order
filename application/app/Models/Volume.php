<?php

namespace App\Models;

use App\Enums\VolumeUnits;
use App\Models\Dto\VolumeAnalysisDiscount;
use App\Services\CatTools\VolumeAnalysis;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Models\Volume
 *
 * @property string $id
 * @property string $assignment_id
 * @property string|null $cat_tool_job_id
 * @property VolumeUnits $unit_type
 * @property string $unit_quantity
 * @property string $unit_fee
 * @property array|null $custom_volume_analysis
 * @property array|null $custom_discounts
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Assignment $assignment
 * @property-read CatToolJob|null $catToolJob
 *
 * @method static Builder|Volume newModelQuery()
 * @method static Builder|Volume newQuery()
 * @method static Builder|Volume onlyTrashed()
 * @method static Builder|Volume query()
 * @method static Builder|Volume whereAssignmentId($value)
 * @method static Builder|Volume whereCatChunkIdentifier($value)
 * @method static Builder|Volume whereCreatedAt($value)
 * @method static Builder|Volume whereDeletedAt($value)
 * @method static Builder|Volume whereId($value)
 * @method static Builder|Volume whereUnitFee($value)
 * @method static Builder|Volume whereUnitQuantity($value)
 * @method static Builder|Volume whereUnitType($value)
 * @method static Builder|Volume whereUpdatedAt($value)
 * @method static Builder|Volume withTrashed()
 * @method static Builder|Volume withoutTrashed()
 *
 * @mixin Eloquent
 */
class Volume extends Model
{
    use HasUuids,
        HasFactory,
        SoftDeletes;

    protected $table = 'volumes';

    protected $guarded = [];

    protected $casts = [
        'unit_type' => VolumeUnits::class,
        'custom_volume_analysis' => AsArrayObject::class,
        'custom_discounts' => AsArrayObject::class,
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class, 'assignment_id');
    }

    public function catToolJob(): BelongsTo
    {
        return $this->belongsTo(CatToolJob::class, 'cat_tool_job_id');
    }

    public function getDiscount(?Vendor $assignee = null): ?VolumeAnalysisDiscount
    {
        if (empty($this->cat_tool_job_id)) {
            return null;
        }

        $assignee = $assignee ?: $this->assignment->assignee;
        if (filled($assignee)) {
            return new VolumeAnalysisDiscount(
                array_merge($this->assignment->assignee?->only(
                    'discount_percentage_101',
                    'discount_percentage_repetitions',
                    'discount_percentage_100',
                    'discount_percentage_95_99',
                    'discount_percentage_85_94',
                    'discount_percentage_75_84',
                    'discount_percentage_50_74',
                    'discount_percentage_0_49'
                ) ?: [], (array) $this->custom_discounts)
            );
        }

        // TODO: use institution discounts in case if vendor is not assigned
        return new VolumeAnalysisDiscount(array_merge([
            'discount_percentage_101' => 0,
            'discount_percentage_repetitions' => 0,
            'discount_percentage_100' => 0,
            'discount_percentage_95_99' => 0,
            'discount_percentage_85_94' => 0,
            'discount_percentage_75_84' => 0,
            'discount_percentage_50_74' => 0,
            'discount_percentage_0_49' => 0,
        ], (array) $this->custom_discounts)
        );
    }

    public function getVolumeAnalysis(): ?VolumeAnalysis
    {
        if (empty($this->cat_tool_job_id)) {
            return null;
        }

        return new VolumeAnalysis(array_merge(
            (array) $this->catToolJob->volume_analysis,
            (array) $this->custom_volume_analysis
        ));
    }
}
