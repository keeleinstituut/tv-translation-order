<?php

namespace App\Models;

use App\Enums\ExternalRequestRecipientStatus;
use App\Models\CachedEntities\Institution;
use Database\Factories\ExternalTranslationRequestRecipientFactory;
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
 * @property string $external_translation_request_id
 * @property string $institution_id
 * @property int $position
 * @property ExternalRequestRecipientStatus $status
 * @property Carbon|null $notified_at
 * @property Carbon|null $responded_at
 * @property Carbon|null $expires_at
 * @property string|null $calculated_price
 * @property string|null $proposed_price
 * @property string|null $decline_comment
 * @property string|null $response_comment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read ExternalTranslationRequest $externalTranslationRequest
 * @property-read Institution $institution
 * @method static Builder|ExternalTranslationRequestRecipient ordered($direction = 'asc')
 */
class ExternalTranslationRequestRecipient extends Model implements Sortable
{
    /** @use HasFactory<ExternalTranslationRequestRecipientFactory> */
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
        'status' => ExternalRequestRecipientStatus::class,
        'position' => 'integer',
        'notified_at' => 'datetime',
        'responded_at' => 'datetime',
        'expires_at' => 'datetime',
        'calculated_price' => 'decimal:3',
        'proposed_price' => 'decimal:3',
    ];

    public function buildSortQuery(): Builder
    {
        return static::query()->where('external_translation_request_id', $this->external_translation_request_id);
    }

    public function externalTranslationRequest(): BelongsTo
    {
        return $this->belongsTo(ExternalTranslationRequest::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function ownerInstitutionPartner(): ?InstitutionPartner
    {
        return InstitutionPartner::query()
            ->where('institution_id', $this->externalTranslationRequest->ownerInstitutionId())
            ->where('partner_institution_id', $this->institution_id)
            ->first();
    }
}
