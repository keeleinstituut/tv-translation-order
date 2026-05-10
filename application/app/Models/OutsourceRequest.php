<?php

namespace App\Models;

use App\Enums\ExternalRequestMode;
use App\Enums\OutsourceOfferStatus;
use App\Enums\OutsourceRequestStatus;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use Database\Factories\OutsourceRequestFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Staudenmeir\EloquentHasManyDeep\HasOneDeep;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

/**
 * @property string $id
 * @property string $assignment_id
 * @property string $institution_user_id
 * @property ExternalRequestMode $mode
 * @property int $reaction_time_minutes
 * @property string|null $special_instructions
 * @property string|null $price
 * @property bool $include_price
 * @property bool $include_source_files
 * @property OutsourceRequestStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Assignment $assignment
 * @property-read InstitutionUser $institutionUser
 * @property-read Collection<int, OutsourceOffer> $offers
 * @property-read OutsourceOffer|null $selectedOffer
 * @property-read Institution|null $ownerInstitution
 */
class OutsourceRequest extends Model implements HasMedia
{
    /** @use HasFactory<OutsourceRequestFactory> */
    use HasFactory;
    use HasRelationships;
    use HasUuids;
    use InteractsWithMedia;
    use SoftDeletes;

    public const REQUEST_FILES_COLLECTION = 'request_files';

    protected $guarded = [];

    protected $casts = [
        'mode' => ExternalRequestMode::class,
        'status' => OutsourceRequestStatus::class,
        'price' => 'decimal:3',
        'include_price' => 'boolean',
        'include_source_files' => 'boolean',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function institutionUser(): BelongsTo
    {
        return $this->belongsTo(InstitutionUser::class, 'institution_user_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(OutsourceOffer::class);
    }

    public function selectedOffer(): HasOne
    {
        return $this->hasOne(OutsourceOffer::class)
            ->where('status', OutsourceOfferStatus::OfferAccepted);
    }

    public function ownerInstitution(): HasOneDeep
    {
        return $this->hasOneDeepFromRelations(
            $this->assignment(),
            new Assignment()->subProject(),
            new SubProject()->project(),
            new Project()->institution(),
        );
    }

    public function isCascade(): bool
    {
        return $this->mode === ExternalRequestMode::Cascade;
    }

    public function effectiveDeadlineAt(): ?Carbon
    {
        if ($this->isCascade() || $this->created_at === null) {
            return null;
        }

        return $this->created_at->copy()->addMinutes($this->reaction_time_minutes);
    }
}
