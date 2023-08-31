<?php

namespace App\Models;

use App\Models\CachedEntities\ClassifierValue;
use Database\Factories\PriceFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Models\Price
 *
 * @property string|null $id
 * @property string|null $vendor_id
 * @property string|null $src_lang_classifier_value_id
 * @property string|null $dst_lang_classifier_value_id
 * @property float|null $character_fee
 * @property float|null $word_fee
 * @property float|null $page_fee
 * @property float|null $minute_fee
 * @property float|null $hour_fee
 * @property float|null $minimal_fee
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $skill_id
 * @property-read ClassifierValue|null $destinationLanguageClassifierValue
 * @property-read Skill|null $skill
 * @property-read ClassifierValue|null $sourceLanguageClassifierValue
 * @property-read Vendor|null $vendor
 *
 * @method static PriceFactory factory($count = null, $state = [])
 * @method static Builder|Price newModelQuery()
 * @method static Builder|Price newQuery()
 * @method static Builder|Price onlyTrashed()
 * @method static Builder|Price query()
 * @method static Builder|Price whereCharacterFee($value)
 * @method static Builder|Price whereCreatedAt($value)
 * @method static Builder|Price whereDeletedAt($value)
 * @method static Builder|Price whereDstLangClassifierValueId($value)
 * @method static Builder|Price whereHourFee($value)
 * @method static Builder|Price whereId($value)
 * @method static Builder|Price whereMinimalFee($value)
 * @method static Builder|Price whereMinuteFee($value)
 * @method static Builder|Price wherePageFee($value)
 * @method static Builder|Price whereSkillId($value)
 * @method static Builder|Price whereSrcLangClassifierValueId($value)
 * @method static Builder|Price whereUpdatedAt($value)
 * @method static Builder|Price whereVendorId($value)
 * @method static Builder|Price whereWordFee($value)
 * @method static Builder|Price withTrashed()
 * @method static Builder|Price withoutTrashed()
 *
 * @mixin Eloquent
 */
class Price extends Model
{
    use HasUuids;
    use HasFactory;
    use SoftDeletes;

    protected $connection = 'pgsql_app';

    protected $table = 'prices';

    protected $fillable = [
        'vendor_id',
        'skill_id',
        'src_lang_classifier_value_id',
        'dst_lang_classifier_value_id',
        'character_fee',
        'word_fee',
        'page_fee',
        'minute_fee',
        'hour_fee',
        'minimal_fee',
    ];

    protected $casts = [
        'character_fee' => 'float',
        'word_fee' => 'float',
        'page_fee' => 'float',
        'minute_fee' => 'float',
        'hour_fee' => 'float',
        'minimal_fee' => 'float',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function sourceLanguageClassifierValue()
    {
        return $this->belongsTo(ClassifierValue::class, 'src_lang_classifier_value_id');
    }

    public function destinationLanguageClassifierValue()
    {
        return $this->belongsTo(ClassifierValue::class, 'dst_lang_classifier_value_id');
    }

    public function skill()
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }
}
