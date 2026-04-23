<?php

namespace App\Models;

use App\Models\CachedEntities\ClassifierValue;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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
