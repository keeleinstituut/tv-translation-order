<?php

namespace App\Models;

use App\Enums\VolumeUnits;
use App\Models\CachedEntities\ClassifierValue;
use Database\Factories\PriceFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $cat_chunk_identifier
 * @property VolumeUnits $unit_type
 * @property string $unit_quantity
 * @property string $unit_fee
 * @property-read Assignment $assignment
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
 * @mixin Eloquent
 */
class Volume extends Model
{
    use HasUuids,
        HasFactory,
        SoftDeletes;

    protected $connection = 'pgsql_app';
    protected $table = 'volumes';

    protected $guarded = [];

    protected $casts = [
        'unit_type' => VolumeUnits::class
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class, 'assignment_id');
    }
}
