<?php

namespace App\Models;

use App\Enums\VolumeUnits;
use App\Models\CachedEntities\Institution;
use App\Models\Dto\VolumeAnalysisDiscount;
use Database\Factories\InstitutionPartnerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $institution_id
 * @property string $partner_institution_id
 * @property float|null $discount_percentage_101
 * @property float|null $discount_percentage_repetitions
 * @property float|null $discount_percentage_100
 * @property float|null $discount_percentage_95_99
 * @property float|null $discount_percentage_85_94
 * @property float|null $discount_percentage_75_84
 * @property float|null $discount_percentage_50_74
 * @property float|null $discount_percentage_0_49
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Institution|null $institution
 * @property-read Institution|null $partnerInstitution
 * @property-read Collection<int, InstitutionPartnerPrice> $prices
 *
 * @method static InstitutionPartnerFactory factory($count = null, $state = [])
 * @method static Builder|InstitutionPartner newModelQuery()
 * @method static Builder|InstitutionPartner newQuery()
 * @method static Builder|InstitutionPartner onlyTrashed()
 * @method static Builder|InstitutionPartner query()
 * @method static Builder|InstitutionPartner withTrashed()
 * @method static Builder|InstitutionPartner withoutTrashed()
 */
class InstitutionPartner extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $guarded = [];

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

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function partnerInstitution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'partner_institution_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(InstitutionPartnerPrice::class);
    }

    public function getVolumeAnalysisDiscount(): VolumeAnalysisDiscount
    {
        return new VolumeAnalysisDiscount([
            'discount_percentage_101' => $this->discount_percentage_101,
            'discount_percentage_repetitions' => $this->discount_percentage_repetitions,
            'discount_percentage_100' => $this->discount_percentage_100,
            'discount_percentage_95_99' => $this->discount_percentage_95_99,
            'discount_percentage_85_94' => $this->discount_percentage_85_94,
            'discount_percentage_75_84' => $this->discount_percentage_75_84,
            'discount_percentage_50_74' => $this->discount_percentage_50_74,
            'discount_percentage_0_49' => $this->discount_percentage_0_49,
        ]);
    }

    public function resolveFee(string $srcLangId, string $dstLangId, ?string $skillId, VolumeUnits $unit): ?float
    {
        if (empty($skillId)) {
            return null;
        }

        /** @var InstitutionPartnerPrice $price */
        $price = $this->prices()
            ->where('src_lang_classifier_value_id', $srcLangId)
            ->where('dst_lang_classifier_value_id', $dstLangId)
            ->where('skill_id', $skillId)
            ->first();

        return $price?->getUnitFee($unit);
    }

    public function resolveMinimalFee(string $srcLangId, string $dstLangId, ?string $skillId): ?float
    {
        if (empty($skillId)) {
            return null;
        }

        return $this->prices()
            ->where('src_lang_classifier_value_id', $srcLangId)
            ->where('dst_lang_classifier_value_id', $dstLangId)
            ->where('skill_id', $skillId)
            ->value('minimal_fee');
    }
}
