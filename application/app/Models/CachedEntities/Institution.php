<?php

namespace App\Models\CachedEntities;

use App\Enums\InstitutionType;
use App\Models\InstitutionDiscount;
use App\Models\InstitutionPartner;
use App\Models\InstitutionPrice;
use App\Models\Sequence;
use Database\Factories\CachedEntities\InstitutionFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Models\CachedEntities\Institution
 *
 * @property string|null $id
 * @property string|null $name
 * @property string|null $short_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $logo_url
 * @property string|null $synced_at
 * @property InstitutionType $institution_type
 * @property Carbon|null $deleted_at
 * @property-read Sequence|null $institutionProjectSequence
 * @property-read InstitutionDiscount|null $institutionDiscount
 *
 * @method static InstitutionFactory factory($count = null, $state = [])
 * @method static Builder|Institution newModelQuery()
 * @method static Builder|Institution newQuery()
 * @method static Builder|Institution onlyTrashed()
 * @method static Builder|Institution query()
 * @method static Builder|Institution whereDeletedAt($value)
 * @method static Builder|Institution whereEmail($value)
 * @method static Builder|Institution whereId($value)
 * @method static Builder|Institution whereLogoUrl($value)
 * @method static Builder|Institution whereName($value)
 * @method static Builder|Institution wherePhone($value)
 * @method static Builder|Institution whereShortName($value)
 * @method static Builder|Institution whereSyncedAt($value)
 * @method static Builder|Institution withTrashed()
 * @method static Builder|Institution withoutTrashed()
 * @property string|null $worktime_timezone
 * @property string|null $monday_worktime_start
 * @property string|null $monday_worktime_end
 * @property string|null $tuesday_worktime_start
 * @property string|null $tuesday_worktime_end
 * @property string|null $wednesday_worktime_start
 * @property string|null $wednesday_worktime_end
 * @property string|null $thursday_worktime_start
 * @property string|null $thursday_worktime_end
 * @property string|null $friday_worktime_start
 * @property string|null $friday_worktime_end
 * @property string|null $saturday_worktime_start
 * @property string|null $saturday_worktime_end
 * @property string|null $sunday_worktime_start
 * @property string|null $sunday_worktime_end
 * @method static Builder<static>|Institution whereFridayWorktimeEnd($value)
 * @method static Builder<static>|Institution whereFridayWorktimeStart($value)
 * @method static Builder<static>|Institution whereMondayWorktimeEnd($value)
 * @method static Builder<static>|Institution whereMondayWorktimeStart($value)
 * @method static Builder<static>|Institution whereSaturdayWorktimeEnd($value)
 * @method static Builder<static>|Institution whereSaturdayWorktimeStart($value)
 * @method static Builder<static>|Institution whereSundayWorktimeEnd($value)
 * @method static Builder<static>|Institution whereSundayWorktimeStart($value)
 * @method static Builder<static>|Institution whereThursdayWorktimeEnd($value)
 * @method static Builder<static>|Institution whereThursdayWorktimeStart($value)
 * @method static Builder<static>|Institution whereTuesdayWorktimeEnd($value)
 * @method static Builder<static>|Institution whereTuesdayWorktimeStart($value)
 * @method static Builder<static>|Institution whereWednesdayWorktimeEnd($value)
 * @method static Builder<static>|Institution whereWednesdayWorktimeStart($value)
 * @method static Builder<static>|Institution whereWorktimeTimezone($value)
 * @mixin Eloquent
 */
class Institution extends Model
{
    use HasUuids, SoftDeletes, HasFactory;

    protected $table = 'cached_institutions';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'institution_type' => InstitutionType::class,
    ];

    public function isTranslationAgency(): bool
    {
        return $this->institution_type === InstitutionType::TranslationAgency;
    }

    public function institutionProjectSequence()
    {
        return $this->morphOne(Sequence::class, 'sequenceable')
            ->where('name', Sequence::INSTITUTION_PROJECT_SEQ);
    }

    public function institutionDiscount(): HasOne
    {
        return $this->hasOne(InstitutionDiscount::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(InstitutionPrice::class);
    }

    public function partners(): HasMany
    {
        return $this->hasMany(InstitutionPartner::class);
    }

    public function partnerOf(): HasMany
    {
        return $this->hasMany(InstitutionPartner::class, 'partner_institution_id');
    }
}
