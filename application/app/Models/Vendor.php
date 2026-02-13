<?php

namespace App\Models;

use App\Models\CachedEntities\InstitutionUser;
use App\Models\Dto\VolumeAnalysisDiscount;
use AuditLogClient\Enums\AuditLogEventObjectType;
use AuditLogClient\Models\AuditLoggable;
use Barryvdh\LaravelIdeHelper\Eloquent;
use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 *
 * @mixin Eloquent
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

    public function institutionUser(): BelongsTo
    {
        return $this->belongsTo(InstitutionUser::class);
    }

    public function prices()
    {
        return $this->hasMany(Price::class);
    }

    public function candidates()
    {
        return $this->hasMany(Candidate::class);
    }


    public function tags()
    {
        return $this
            ->morphToMany(Tag::class, 'taggable')
            ->using(Taggable::class);
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
