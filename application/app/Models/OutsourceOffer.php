<?php

namespace App\Models;

use App\Enums\OutsourceOfferStatus;
use App\Models\CachedEntities\Institution;
use Database\Factories\OutsourceOfferFactory;
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
 * @property string $id
 * @property string $outsource_request_id
 * @property string $institution_id
 * @property int $position
 * @property OutsourceOfferStatus $status
 * @property Carbon|null $notified_at
 * @property Carbon|null $responded_at
 * @property Carbon|null $expires_at
 * @property string|null $price
 * @property string|null $decline_comment
 * @property string|null $rejection_comment
 * @property string|null $response_comment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read OutsourceRequest $outsourceRequest
 * @property-read Institution $institution
 * @method static Builder|OutsourceOffer ordered($direction = 'asc')
 */
class OutsourceOffer extends Model implements Sortable
{
    /** @use HasFactory<OutsourceOfferFactory> */
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
        'status' => OutsourceOfferStatus::class,
        'position' => 'integer',
        'notified_at' => 'datetime',
        'responded_at' => 'datetime',
        'expires_at' => 'datetime',
        'price' => 'decimal:3',
    ];

    public function buildSortQuery(): Builder
    {
        return static::query()->where('outsource_request_id', $this->outsource_request_id);
    }

    public function outsourceRequest(): BelongsTo
    {
        return $this->belongsTo(OutsourceRequest::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
