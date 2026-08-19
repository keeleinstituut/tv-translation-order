<?php

namespace App\Models;

use App\Models\CachedEntities\ClassifierValue;
use Database\Factories\VendorSkillLanguageFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string|null $id
 * @property string|null $vendor_id
 * @property string|null $skill_id
 * @property string|null $src_lang_classifier_value_id
 * @property string|null $dst_lang_classifier_value_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read ClassifierValue|null $destinationLanguageClassifierValue
 * @property-read Skill|null $skill
 * @property-read ClassifierValue|null $sourceLanguageClassifierValue
 * @property-read Vendor|null $vendor
 *
 * @method static VendorSkillLanguageFactory factory($count = null, $state = [])
 * @method static Builder|VendorSkillLanguage newModelQuery()
 * @method static Builder|VendorSkillLanguage newQuery()
 * @method static Builder|VendorSkillLanguage query()
 *
 * @mixin Eloquent
 */
class VendorSkillLanguage extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'vendor_skill_languages';

    protected $guarded = [];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }

    public function sourceLanguageClassifierValue(): BelongsTo
    {
        return $this->belongsTo(ClassifierValue::class, 'src_lang_classifier_value_id');
    }

    public function destinationLanguageClassifierValue(): BelongsTo
    {
        return $this->belongsTo(ClassifierValue::class, 'dst_lang_classifier_value_id');
    }
}
