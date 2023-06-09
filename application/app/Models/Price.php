<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Price extends Model
{
    use HasUuids;
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        //
        // TODO: add skill
        //
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
}
