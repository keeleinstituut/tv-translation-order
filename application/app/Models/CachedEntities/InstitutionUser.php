<?php

namespace App\Models\CachedEntities;

use App\Enums\PrivilegeKey;
use App\Models\Vendor;
use ArrayObject;
use Database\Factories\CachedEntities\InstitutionUserFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use SyncTools\Traits\HasCachedEntityFactory;
use SyncTools\Traits\IsCachedEntity;

/**
 * App\Models\CachedEntities\InstitutionUser
 *
 * @property string|null $id
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $deactivation_date
 * @property Carbon|null $archived_at
 * @property ArrayObject|null $user
 * @property ArrayObject|null $institution
 * @property ArrayObject|null $department
 * @property ArrayObject|null $roles
 * @property Carbon|null $deleted_at
 * @property Carbon|null $synced_at
 * @property-read Vendor|null $vendor
 *
 * @method static InstitutionUserFactory factory($count = null, $state = [])
 * @method static Builder|InstitutionUser newModelQuery()
 * @method static Builder|InstitutionUser newQuery()
 * @method static Builder|InstitutionUser onlyTrashed()
 * @method static Builder|InstitutionUser query()
 * @method static Builder|InstitutionUser whereArchivedAt($value)
 * @method static Builder|InstitutionUser whereDeactivationDate($value)
 * @method static Builder|InstitutionUser whereDeletedAt($value)
 * @method static Builder|InstitutionUser whereDepartment($value)
 * @method static Builder|InstitutionUser whereEmail($value)
 * @method static Builder|InstitutionUser whereId($value)
 * @method static Builder|InstitutionUser whereInstitution($value)
 * @method static Builder|InstitutionUser wherePhone($value)
 * @method static Builder|InstitutionUser whereRoles($value)
 * @method static Builder|InstitutionUser whereSyncedAt($value)
 * @method static Builder|InstitutionUser whereUser($value)
 * @method static Builder|InstitutionUser withTrashed()
 * @method static Builder|InstitutionUser withoutTrashed()
 *
 * @mixin Eloquent
 */
class InstitutionUser extends Model
{
    use IsCachedEntity, HasCachedEntityFactory, HasUuids, SoftDeletes;

    protected $table = 'cached_institution_users';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'user' => AsArrayObject::class,
        'institution' => AsArrayObject::class,
        'department' => AsArrayObject::class,
        'roles' => AsArrayObject::class,
        'deactivaton_date' => 'datetime',
        'archived_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->hasOne(Vendor::class);
    }

    public function isArchived(): bool
    {
        return filled($this->archived_at);
    }

    public function isDeactivated(): bool
    {
        return filled($this->deactivation_date)
            && ! Date::parse($this->deactivation_date, 'Europe/Tallinn')->isFuture();
    }

    public function hasPrivileges(PrivilegeKey ...$expectedPrivileges): bool
    {
        /** @var Collection<PrivilegeKey> $actualPrivileges */
        $actualPrivileges = collect($this->roles)
            ->filter(fn (array $role) => empty($role['deleted_at']))
            ->flatMap(fn (array $role) => $role['privileges'])
            ->map(PrivilegeKey::from(...));

        return collect($expectedPrivileges)
            ->every(fn (PrivilegeKey $expectedPrivilege) => $actualPrivileges->contains($expectedPrivilege));
    }
}
