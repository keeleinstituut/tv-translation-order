<?php

namespace App\Models;

use App\Enums\ExternalRequestMode;
use App\Enums\ExternalRequestRecipientStatus;
use App\Enums\ExternalRequestStatus;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use Database\Factories\ExternalTranslationRequestFactory;
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
 * @property string $created_by_institution_user_id
 * @property ExternalRequestMode $mode
 * @property int|null $reaction_time_minutes
 * @property Carbon|null $deadline_at
 * @property string|null $special_instructions
 * @property string|null $price
 * @property bool $include_price
 * @property bool $include_source_files
 * @property ExternalRequestStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Assignment $assignment
 * @property-read InstitutionUser $createdByInstitutionUser
 * @property-read Collection<int, ExternalTranslationRequestRecipient> $recipients
 * @property-read ExternalTranslationRequestRecipient|null $selectedRecipient
 * @property-read Institution|null $ownerInstitution
 */
class ExternalTranslationRequest extends Model implements HasMedia
{
    /** @use HasFactory<ExternalTranslationRequestFactory> */
    use HasFactory;
    use HasRelationships;
    use HasUuids;
    use InteractsWithMedia;
    use SoftDeletes;

    public const REQUEST_FILES_COLLECTION = 'request_files';

    protected $guarded = [];

    protected $casts = [
        'mode' => ExternalRequestMode::class,
        'status' => ExternalRequestStatus::class,
        'deadline_at' => 'datetime',
        'price' => 'decimal:3',
        'include_price' => 'boolean',
        'include_source_files' => 'boolean',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function createdByInstitutionUser(): BelongsTo
    {
        return $this->belongsTo(InstitutionUser::class, 'created_by_institution_user_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(ExternalTranslationRequestRecipient::class);
    }

    public function selectedRecipient(): HasOne
    {
        return $this->hasOne(ExternalTranslationRequestRecipient::class)
            ->where('status', ExternalRequestRecipientStatus::Selected);
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
}
