<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
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
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $synced_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @method static \Database\Factories\InstitutionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Institution newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Institution newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Institution onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Institution query()
 * @method static \Illuminate\Database\Eloquent\Builder|Institution whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Institution whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Institution whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Institution whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Institution whereLogoUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Institution whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Institution wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Institution whereShortName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Institution whereSyncedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Institution whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Institution withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Institution withoutTrashed()
 * @mixin \Eloquent
 */
class Institution extends Model
{
    use HasCachedEntityFactory, HasUuids, SoftDeletes;

    protected $table = 'entity_cache.cached_institutions';
}
