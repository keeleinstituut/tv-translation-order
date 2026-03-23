<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $vendor_id
 * @property Carbon $date_from
 * @property Carbon $date_to
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, \App\Models\VendorCalendarEntry> $events
 * @property-read int|null $events_count
 * @property-read \App\Models\Vendor $vendor
 * @method static Builder<static>|VendorCalendarImport newModelQuery()
 * @method static Builder<static>|VendorCalendarImport newQuery()
 * @method static Builder<static>|VendorCalendarImport query()
 * @method static Builder<static>|VendorCalendarImport whereCreatedAt($value)
 * @method static Builder<static>|VendorCalendarImport whereDateFrom($value)
 * @method static Builder<static>|VendorCalendarImport whereDateTo($value)
 * @method static Builder<static>|VendorCalendarImport whereId($value)
 * @method static Builder<static>|VendorCalendarImport whereUpdatedAt($value)
 * @method static Builder<static>|VendorCalendarImport whereVendorId($value)
 * @mixin \Eloquent
 */
class VendorCalendarImport extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'vendor_calendar_imports';

    protected $guarded = [];

    protected $casts = [
        'date_from' => 'datetime',
        'date_to' => 'datetime',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(VendorCalendarEntry::class);
    }
}
