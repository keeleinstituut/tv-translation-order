<?php

namespace App\Models\CachedEntities;

use App\Enums\PrivilegeKey;
use App\Helpers\DateUtil;
use App\Models\InstitutionDiscount;
use App\Models\Vendor;
use ArrayObject;
use Database\Factories\CachedEntities\InstitutionUserFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * App\Models\CachedEntities\InstitutionUser
 *
 * @property string|null $id
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $deactivation_date
 * @property string|null $institution_id
 * @property Carbon|null $archived_at
 * @property ArrayObject|null $user
 * @property ArrayObject|null $institution
 * @property ArrayObject|null $department
 * @property ArrayObject|null $roles
 * @property ArrayObject|null $vacations
 * @property Carbon|null $deleted_at
 * @property Carbon|null $synced_at
 * @property-read Vendor|null $vendor
 * @property-read InstitutionDiscount|null $institutionDiscount
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
 * @method static Builder<static>|InstitutionUser whereFridayWorktimeEnd($value)
 * @method static Builder<static>|InstitutionUser whereFridayWorktimeStart($value)
 * @method static Builder<static>|InstitutionUser whereMondayWorktimeEnd($value)
 * @method static Builder<static>|InstitutionUser whereMondayWorktimeStart($value)
 * @method static Builder<static>|InstitutionUser whereSaturdayWorktimeEnd($value)
 * @method static Builder<static>|InstitutionUser whereSaturdayWorktimeStart($value)
 * @method static Builder<static>|InstitutionUser whereSundayWorktimeEnd($value)
 * @method static Builder<static>|InstitutionUser whereSundayWorktimeStart($value)
 * @method static Builder<static>|InstitutionUser whereThursdayWorktimeEnd($value)
 * @method static Builder<static>|InstitutionUser whereThursdayWorktimeStart($value)
 * @method static Builder<static>|InstitutionUser whereTuesdayWorktimeEnd($value)
 * @method static Builder<static>|InstitutionUser whereTuesdayWorktimeStart($value)
 * @method static Builder<static>|InstitutionUser whereVacations($value)
 * @method static Builder<static>|InstitutionUser whereWednesdayWorktimeEnd($value)
 * @method static Builder<static>|InstitutionUser whereWednesdayWorktimeStart($value)
 * @method static Builder<static>|InstitutionUser whereWorktimeTimezone($value)
 * @mixin Eloquent
 */
class InstitutionUser extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'cached_institution_users';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'user' => AsArrayObject::class,
        'institution' => AsArrayObject::class,
        'department' => AsArrayObject::class,
        'roles' => AsArrayObject::class,
        'vacations' => AsArrayObject::class,
        'deactivation_date' => 'datetime',
        'archived_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function vendor(): HasOne
    {
        return $this->hasOne(Vendor::class);
    }

    public function institutionDiscount(): HasOne
    {
        return $this->hasOne(InstitutionDiscount::class, 'institution_id', 'institution_id');
    }

    public function getInstitutionIdAttribute()
    {
        return $this->institution['id'] ?? null;
    }

    public function getUserFullName(): ?string
    {
        return implode(' ', [
            data_get($this->user, 'forename'),
            data_get($this->user, 'surname')
        ]);
    }

    public function getDepartmentName(): ?string
    {
        return data_get($this->department, 'name');
    }

    public function isArchived(): bool
    {
        return filled($this->archived_at);
    }

    public function isDeactivated(): bool
    {
        return filled($this->deactivation_date)
            && !Date::parse($this->deactivation_date, DateUtil::TIMEZONE)->isFuture();
    }

    public function hasPrivileges(PrivilegeKey ...$expectedPrivileges): bool
    {
        /** @var Collection<PrivilegeKey> $actualPrivileges */
        $actualPrivileges = collect($this->roles)
            ->filter(fn(array $role) => empty($role['deleted_at']))
            ->flatMap(fn(array $role) => $role['privileges'])
            ->map(fn(array $privilege) => $privilege['key'])
            ->map(fn(string $key) => PrivilegeKey::tryFrom($key))
            ->filter();

        return collect($expectedPrivileges)
            ->every(fn(PrivilegeKey $expectedPrivilege) => $actualPrivileges->contains($expectedPrivilege));
    }
}
