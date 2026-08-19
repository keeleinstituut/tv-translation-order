<?php

namespace App\Models;

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
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Vendor $vendor
 * @method static \Database\Factories\VendorEmergencyScheduleFactory factory($count = null, $state = [])
 * @method static Builder<static>|VendorEmergencySchedule newModelQuery()
 * @method static Builder<static>|VendorEmergencySchedule newQuery()
 * @method static Builder<static>|VendorEmergencySchedule onlyTrashed()
 * @method static Builder<static>|VendorEmergencySchedule query()
 * @method static Builder<static>|VendorEmergencySchedule withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|VendorEmergencySchedule withoutTrashed()
 * @mixin \Eloquent
 */
class VendorEmergencySchedule extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'vendor_emergency_schedules';

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
