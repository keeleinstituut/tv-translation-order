<?php

namespace App\Models;

use App\Enums\VolumeUnits;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use Database\Factories\InstitutionPriceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $institution_id
 * @property string $src_lang_classifier_value_id
 * @property string $dst_lang_classifier_value_id
 * @property string $skill_id
 * @property float $character_fee
 * @property float $word_fee
 * @property float $page_fee
 * @property float $minute_fee
 * @property float $hour_fee
 * @property float $minimal_fee
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Institution|null $institution
 * @property-read ClassifierValue|null $sourceLanguageClassifierValue
 * @property-read ClassifierValue|null $destinationLanguageClassifierValue
 * @property-read Skill|null $skill
 *
 * @method static InstitutionPriceFactory factory($count = null, $state = [])
 * @method static Builder|InstitutionPrice newModelQuery()
 * @method static Builder|InstitutionPrice newQuery()
 * @method static Builder|InstitutionPrice onlyTrashed()
 * @method static Builder|InstitutionPrice query()
 * @method static Builder|InstitutionPrice withTrashed()
 * @method static Builder|InstitutionPrice withoutTrashed()
 */
class InstitutionPrice extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'character_fee' => 'decimal:3',
        'word_fee' => 'decimal:3',
        'page_fee' => 'decimal:3',
        'minute_fee' => 'decimal:3',
        'hour_fee' => 'decimal:3',
        'minimal_fee' => 'decimal:3',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function sourceLanguageClassifierValue(): BelongsTo
    {
        return $this->belongsTo(ClassifierValue::class, 'src_lang_classifier_value_id');
    }

    public function destinationLanguageClassifierValue(): BelongsTo
    {
        return $this->belongsTo(ClassifierValue::class, 'dst_lang_classifier_value_id');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }

    public function getUnitFee(VolumeUnits|string $unit): ?float
    {
        is_string($unit) && $unit = VolumeUnits::tryFrom($unit);

        return match ($unit) {
            VolumeUnits::Characters => $this->character_fee,
            VolumeUnits::Words => $this->word_fee,
            VolumeUnits::Pages => $this->page_fee,
            VolumeUnits::Minutes => $this->minute_fee,
            VolumeUnits::Hours => $this->hour_fee,
            VolumeUnits::MinimalFee => $this->minimal_fee,
            default => null,
        };
    }
}
