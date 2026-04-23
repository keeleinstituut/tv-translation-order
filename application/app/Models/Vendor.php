<?php

namespace App\Models;

use App\Models\CachedEntities\InstitutionUser;
use App\Models\Dto\VolumeAnalysisDiscount;
use App\Repositories\Calendar\VendorLanguageCoverageRepository;
use AuditLogClient\Enums\AuditLogEventObjectType;
use AuditLogClient\Models\AuditLoggable;
use Barryvdh\LaravelIdeHelper\Eloquent;
use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Models\Vendor
 *
 * @property string|null $id
 * @property string|null $institution_user_id
 * @property string|null $company_name
 * @property string|null $comment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property float|null $discount_percentage_101
 * @property float|null $discount_percentage_repetitions
 * @property float|null $discount_percentage_100
 * @property float|null $discount_percentage_95_99
 * @property float|null $discount_percentage_85_94
 * @property float|null $discount_percentage_75_84
 * @property float|null $discount_percentage_50_74
 * @property float|null $discount_percentage_0_49
 * @property Carbon|null $deleted_at
 * @property-read InstitutionUser|null $institutionUser
 * @property-read Collection<int, Price> $prices
 * @property-read Collection<int, Candidate> $candidates
 * @property-read int|null $prices_count
 * @property-read Collection<int, Tag> $tags
 * @property-read int|null $tags_count
 *
 * @method static VendorFactory factory($count = null, $state = [])
 * @method static Builder|Vendor newModelQuery()
 * @method static Builder|Vendor newQuery()
 * @method static Builder|Vendor onlyTrashed()
 * @method static Builder|Vendor query()
 * @method static Builder|Vendor whereComment($value)
 * @method static Builder|Vendor whereCompanyName($value)
 * @method static Builder|Vendor whereCreatedAt($value)
 * @method static Builder|Vendor whereDeletedAt($value)
 * @method static Builder|Vendor whereDiscountPercentage049($value)
 * @method static Builder|Vendor whereDiscountPercentage100($value)
 * @method static Builder|Vendor whereDiscountPercentage101($value)
 * @method static Builder|Vendor whereDiscountPercentage5074($value)
 * @method static Builder|Vendor whereDiscountPercentage7584($value)
 * @method static Builder|Vendor whereDiscountPercentage8594($value)
 * @method static Builder|Vendor whereDiscountPercentage9599($value)
 * @method static Builder|Vendor whereDiscountPercentageRepetitions($value)
 * @method static Builder|Vendor whereId($value)
 * @method static Builder|Vendor whereInstitutionUserId($value)
 * @method static Builder|Vendor whereUpdatedAt($value)
 * @method static Builder|Vendor withTrashed()
 * @method static Builder|Vendor withoutTrashed()
 * @mixin Eloquent
 * @property-read bool $is_internal
 * @property-read Collection<int, VendorCalendarEntry> $calendarEntries
 * @property-read int|null $calendar_entries_count
 * @property-read Collection<int, VendorCalendarImport> $calendarImports
 * @property-read int|null $calendar_imports_count
 * @property-read Collection<int, VendorEmergencySchedule> $emergencySchedules
 * @property-read int|null $emergency_schedules_count
 * @property-read int|null $candidates_count
 * @property-read Taggable|null $pivot
 * @method static Builder<static>|Vendor availableForSlot(\Illuminate\Support\Carbon $startAt, \Illuminate\Support\Carbon $endAt, ?string $excludePrebookUserId = null)
 * @method static Builder<static>|Vendor servingLanguage(string $languageId, string $institutionId)
 * @method static Builder<static>|Vendor withCalendarImportFor(\Illuminate\Support\Carbon $date)
 * @mixin \Eloquent
 */
class Vendor extends Model implements AuditLoggable
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'vendors';

    protected $fillable = [
        'institution_user_id',
        'company_name',
        'comment',
        'discount_percentage_101',
        'discount_percentage_repetitions',
        'discount_percentage_100',
        'discount_percentage_95_99',
        'discount_percentage_85_94',
        'discount_percentage_75_84',
        'discount_percentage_50_74',
        'discount_percentage_0_49',
    ];

    protected $casts = [
        'discount_percentage_101' => 'float',
        'discount_percentage_repetitions' => 'float',
        'discount_percentage_100' => 'float',
        'discount_percentage_95_99' => 'float',
        'discount_percentage_85_94' => 'float',
        'discount_percentage_75_84' => 'float',
        'discount_percentage_50_74' => 'float',
        'discount_percentage_0_49' => 'float',
    ];

    protected function isInternal(): Attribute
    {
        return Attribute::get(fn () => empty($this->company_name));
    }

    public function institutionUser(): BelongsTo
    {
        return $this->belongsTo(InstitutionUser::class);
    }

    public function prices()
    {
        return $this->hasMany(Price::class);
    }

    public function skillLanguagePairs(): HasMany
    {
        return $this->hasMany(VendorSkillLanguagePair::class);
    }

    public function candidates()
    {
        return $this->hasMany(Candidate::class);
    }

    public function calendarEntries(): HasMany
    {
        return $this->hasMany(VendorCalendarEntry::class);
    }

    public function calendarImports(): HasMany
    {
        return $this->hasMany(VendorCalendarImport::class);
    }

    public function emergencySchedules(): HasMany
    {
        return $this->hasMany(VendorEmergencySchedule::class);
    }

    public function tags()
    {
        return $this
            ->morphToMany(Tag::class, 'taggable')
            ->using(Taggable::class);
    }

    /** Vendors who serve a language at an institution. */
    public function scopeServingLanguage(Builder $query, string $languageId, string $institutionId): Builder
    {
        $repo = app(VendorLanguageCoverageRepository::class);
        $vendorIds = $repo->getVendorIdsForLanguage($languageId, $institutionId);

        return $query->whereIn('id', $vendorIds);
    }

    /** Vendors who have imported their calendar covering a date (required for matching). */
    public function scopeWithCalendarImportFor(Builder $query, Carbon $date): Builder
    {
        return $query->whereHas(
            'calendarImports',
            fn (Builder $sub) => $sub
                ->where('date_from', '<=', $date->copy()->endOfDay())
                ->where('date_to', '>=', $date->copy()->startOfDay())
        );
    }

    /** Vendors with no conflicting booking in this slot. */
    public function scopeAvailableForSlot(Builder $query, Carbon $startAt, Carbon $endAt, ?string $excludePrebookUserId = null): Builder
    {
        return $query->whereDoesntHave(
            'calendarEntries',
            fn (Builder $sub) => $sub->overlapping($startAt, $endAt)
                ->when($excludePrebookUserId, fn (Builder $q) => $q
                    ->where(fn (Builder $inner) => $inner
                        ->whereNull('prebook_institution_user_id')
                        ->orWhere('prebook_institution_user_id', '!=', $excludePrebookUserId)
                    )
                )
        );
    }

    /** Vendors who do NOT have an active emergency schedule covering the given date. */
    public function scopeWithoutActiveEmergencySchedule(Builder $query, Carbon $date): Builder
    {
        return $query->whereDoesntHave(
            'emergencySchedules',
            fn (Builder $sub) => $sub
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date)
        );
    }

    public function getVolumeAnalysisDiscount(): VolumeAnalysisDiscount
    {
        $discountAttributes = [
            'discount_percentage_101',
            'discount_percentage_repetitions',
            'discount_percentage_100',
            'discount_percentage_95_99',
            'discount_percentage_85_94',
            'discount_percentage_75_84',
            'discount_percentage_50_74',
            'discount_percentage_0_49',
        ];

        $hasNoDiscounts = collect($this->only($discountAttributes))->filter()->isEmpty();

        if ($hasNoDiscounts && filled($institutionDiscount = $this->institutionUser?->institutionDiscount)) {
            return new VolumeAnalysisDiscount($institutionDiscount->only($discountAttributes));
        }

        return new VolumeAnalysisDiscount($this->only($discountAttributes));
    }

    public function getPriceList(string $sourceLanguageId, string $destinationLanguageId, ?string $skillId = null): ?Price
    {
        if (empty($skillId)) {
            return null;
        }

        return $this->prices()->where(
            'src_lang_classifier_value_id', $sourceLanguageId
        )->where(
            'dst_lang_classifier_value_id', $destinationLanguageId
        )->where(
            'skill_id', $skillId
        )->first();
    }

    public function getIdentitySubset(): array
    {
        return [
            'id' => $this->id,
            'institution_user' => [
                'id' => $this->institution_user_id,
                'user' => [
                    'id' => data_get($this->institutionUser, 'user.id'),
                    'personal_identification_code' => data_get($this->institutionUser, 'user.personal_identification_code'),
                    'forename' => data_get($this->institutionUser, 'user.forename'),
                    'surname' => data_get($this->institutionUser, 'user.surname'),
                ],
            ],
        ];
    }

    public function getAuditLogRepresentation(): array
    {
        return $this->withoutRelations()
            ->refresh()
            ->load([
                'institutionUser',
                'prices',
                'prices.destinationLanguageClassifierValue',
                'prices.sourceLanguageClassifierValue',
                'prices.skill',
            ])
            ->toArray();
    }

    public function getAuditLogObjectType(): AuditLogEventObjectType
    {
        return AuditLogEventObjectType::Vendor;
    }
}
