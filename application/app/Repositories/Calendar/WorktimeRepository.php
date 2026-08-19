<?php

namespace App\Repositories\Calendar;

use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Services\Calendar\VendorWorkingHoursResolver;
use Illuminate\Support\Collection;

readonly class WorktimeRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public function getInstitutionWorktime(string $institutionId): ?array
    {
        return Institution::query()
            ->where('id', $institutionId)
            ->first(VendorWorkingHoursResolver::WORKTIME_COLUMNS)
            ?->toArray();
    }

    /**
     * @param  Collection<int, string>  $institutionUserIds
     * @return Collection<string, array<string, mixed>>  keyed by institution_user_id
     */
    public function getUserWorktimes(Collection $institutionUserIds): Collection
    {
        if ($institutionUserIds->isEmpty()) {
            return collect();
        }

        /** @var Collection $worktimes */
        $worktimes = InstitutionUser::query()
            ->whereIn('id', $institutionUserIds)
            ->get(array_merge(['id'], VendorWorkingHoursResolver::WORKTIME_COLUMNS))
            ->keyBy('id')
            ->map->toArray();

        return $worktimes;
    }
}
