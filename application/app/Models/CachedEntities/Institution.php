<?php

namespace App\Models\CachedEntities;

use App\Models\Sequence;
use Database\Factories\CachedEntities\InstitutionFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use SyncTools\Traits\HasCachedEntityFactory;
use SyncTools\Traits\IsCachedEntity;

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
 * @property Carbon|null $deleted_at
 * @property-read Sequence|null $institutionProjectSequence
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
 *
 * @mixin Eloquent
 */
class Institution extends Model
{
    use IsCachedEntity, HasCachedEntityFactory, HasUuids, SoftDeletes;

    protected $table = 'cached_institutions';

    public $timestamps = false;

    public function institutionProjectSequence()
    {
        return $this->morphOne(Sequence::class, 'sequenceable')
            ->where('name', Sequence::INSTITUTION_PROJECT_SEQ);
    }
}
