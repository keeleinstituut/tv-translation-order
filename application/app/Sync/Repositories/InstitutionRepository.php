<?php

namespace App\Sync\Repositories;

use App\Enums\InstitutionType;
use App\Models\CachedEntities\Institution;
use Carbon\Carbon;
use RuntimeException;
use SyncTools\Repositories\CachedEntityRepositoryInterface;

class InstitutionRepository implements CachedEntityRepositoryInterface
{
    public function save(array $resource): void
    {
        $obj = $this->getBaseModel()->withTrashed()->find($resource['id']);

        if (! $obj) {
            $obj = $this->getBaseModel();
            $obj->id = $resource['id'];
        }

        if (!empty($resource['institution_type']) && empty(InstitutionType::tryFrom($resource['institution_type']))) {
            throw new RuntimeException('Unregistered Institution Type provided');
        }

        $obj->name = $resource['name'];
        $obj->short_name = $resource['short_name'];
        $obj->phone = $resource['phone'];
        $obj->email = $resource['email'];
        $obj->logo_url = $resource['logo_url'];
        $obj->deleted_at = $resource['deleted_at'];
        $obj->synced_at = Carbon::now();
        $obj->institution_type = InstitutionType::tryFrom($resource['institution_type'] ?? '') ?: InstitutionType::Institution;

        $obj->worktime_timezone = $resource['worktime_timezone'] ?? null;
        $obj->monday_worktime_start = $resource['monday_worktime_start'] ?? null;
        $obj->monday_worktime_end = $resource['monday_worktime_end'] ?? null;
        $obj->tuesday_worktime_start = $resource['tuesday_worktime_start'] ?? null;
        $obj->tuesday_worktime_end = $resource['tuesday_worktime_end'] ?? null;
        $obj->wednesday_worktime_start = $resource['wednesday_worktime_start'] ?? null;
        $obj->wednesday_worktime_end = $resource['wednesday_worktime_end'] ?? null;
        $obj->thursday_worktime_start = $resource['thursday_worktime_start'] ?? null;
        $obj->thursday_worktime_end = $resource['thursday_worktime_end'] ?? null;
        $obj->friday_worktime_start = $resource['friday_worktime_start'] ?? null;
        $obj->friday_worktime_end = $resource['friday_worktime_end'] ?? null;
        $obj->saturday_worktime_start = $resource['saturday_worktime_start'] ?? null;
        $obj->saturday_worktime_end = $resource['saturday_worktime_end'] ?? null;
        $obj->sunday_worktime_start = $resource['sunday_worktime_start'] ?? null;
        $obj->sunday_worktime_end = $resource['sunday_worktime_end'] ?? null;

        $obj->save();
    }

    public function delete(string $id): void
    {
        if ($obj = $this->getBaseModel()->find($id)) {
            $obj->delete();
        }
    }

    public function deleteNotSynced(): void
    {
        $this->getBaseModel()->newQuery()->whereNull('synced_at')
            ->delete();
    }

    public function cleanupLastSyncDateTime(): void
    {
        $this->getBaseModel()->newQuery()->update(['synced_at' => null]);
    }

    private function getBaseModel(): Institution
    {
        return Institution::getModel();
    }
}
