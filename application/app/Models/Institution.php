<?php

namespace App\Models;

use Database\Factories\InstitutionFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use SyncTools\Traits\HasCachedEntityFactory;

/**
 * App\Models\Institution
 *
 * @property string $id
 * @property string $name
 * @property string|null $short_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $logo_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $synced_at
 * @property Carbon|null $deleted_at
 *
 * @method static InstitutionFactory factory($count = null, $state = [])
 * @method static Builder|Institution newModelQuery()
 * @method static Builder|Institution newQuery()
 * @method static Builder|Institution onlyTrashed()
 * @method static Builder|Institution query()
 * @method static Builder|Institution whereCreatedAt($value)
 * @method static Builder|Institution whereDeletedAt($value)
 * @method static Builder|Institution whereEmail($value)
 * @method static Builder|Institution whereId($value)
 * @method static Builder|Institution whereLogoUrl($value)
 * @method static Builder|Institution whereName($value)
 * @method static Builder|Institution wherePhone($value)
 * @method static Builder|Institution whereShortName($value)
 * @method static Builder|Institution whereSyncedAt($value)
 * @method static Builder|Institution whereUpdatedAt($value)
 * @method static Builder|Institution withTrashed()
 * @method static Builder|Institution withoutTrashed()
 *
 * @mixin Eloquent
 *
 */
class Institution extends Model
{
    use HasCachedEntityFactory, HasUuids, SoftDeletes;

    protected $table = 'entity_cache.cached_institutions';
}
