<?php

namespace App\Models;

use App\Models\CachedEntities\ClassifierValue;
use Database\Factories\VendorSkillLanguagePairFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $vendor_id
 * @property string $src_lang_classifier_value_id
 * @property string $dst_lang_classifier_value_id
 * @property string $skill_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Vendor $vendor
 * @property-read ClassifierValue|null $sourceLanguageClassifierValue
 * @property-read ClassifierValue|null $destinationLanguageClassifierValue
 * @property-read Skill|null $skill
 * @method static VendorSkillLanguagePairFactory factory($count = null, $state = [])
 * @method static Builder|VendorSkillLanguagePair newModelQuery()
 * @method static Builder|VendorSkillLanguagePair newQuery()
 * @method static Builder|VendorSkillLanguagePair onlyTrashed()
 * @method static Builder|VendorSkillLanguagePair query()
 * @method static Builder|VendorSkillLanguagePair withTrashed()
 * @method static Builder|VendorSkillLanguagePair withoutTrashed()
 */
class VendorSkillLanguagePair extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $guarded = [];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
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
}
