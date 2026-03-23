<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string|null $price_id
 * @property string|null $vendor_id
 * @property string|null $language_id
 * @property string|null $skill_id
 * @property string|null $skill_code
 * @property string|null $institution_id
 * @property string|null $institution_user_id
 * @property bool|null $is_internal
 * @property-read Vendor|null $vendor
 * @method static Builder<static>|VendorLanguageCoverage newModelQuery()
 * @method static Builder<static>|VendorLanguageCoverage newQuery()
 * @method static Builder<static>|VendorLanguageCoverage query()
 * @method static Builder<static>|VendorLanguageCoverage whereInstitutionId($value)
 * @method static Builder<static>|VendorLanguageCoverage whereInstitutionUserId($value)
 * @method static Builder<static>|VendorLanguageCoverage whereIsInternal($value)
 * @method static Builder<static>|VendorLanguageCoverage whereLanguageId($value)
 * @method static Builder<static>|VendorLanguageCoverage wherePriceId($value)
 * @method static Builder<static>|VendorLanguageCoverage whereSkillCode($value)
 * @method static Builder<static>|VendorLanguageCoverage whereSkillId($value)
 * @method static Builder<static>|VendorLanguageCoverage whereVendorId($value)
 * @mixin \Eloquent
 */
class VendorLanguageCoverage extends Model
{
    protected $table = 'v_vendor_language_coverage';

    protected $primaryKey = 'price_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $casts = [
        'is_internal' => 'boolean',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
