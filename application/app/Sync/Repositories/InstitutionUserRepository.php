<?php

namespace App\Sync\Repositories;

use App\Models\CachedEntities\InstitutionUser;
use App\Models\Vendor;
use App\Models\VendorCalendarEntry;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use SyncTools\Repositories\CachedEntityRepositoryInterface;

class InstitutionUserRepository implements CachedEntityRepositoryInterface
{
    public function save(array $resource): void
    {
        $obj = $this->getBaseModel()->withTrashed()->find($resource['id']);

        if (! $obj) {
            $obj = $this->getBaseModel();
            $obj->id = $resource['id'];
        }

        $obj->email = $resource['email'];
        $obj->phone = $resource['phone'];
        $obj->archived_at = $resource['archived_at'];
        $obj->deactivation_date = $resource['deactivation_date'];
        $obj->user = $this->getNestedResourceAsJson(
            $resource, 'user', [
                'id',
                'personal_identification_code',
                'forename',
                'surname',
            ]);
        $obj->institution = $this->getNestedResourceAsJson(
            $resource, 'institution', [
                'id',
                'name',
                'short_name',
                'phone',
                'email',
                'logo_url',
            ]);
        $obj->department = $this->getNestedResourceAsJson(
            $resource, 'department', [
                'id',
                'institution_id',
                'name',
            ]);
        $obj->roles = $this->getNestedResourceAsJson(
            $resource, 'roles', [
                'id',
                'name',
                'institution_id',
                'privileges',
            ]);

        $vacations = array_merge(
            $this->getNestedResourceAsJson(
                $resource, 'vacations.institution_user_vacations', [
                    'id',
                    'start_date',
                    'end_date',
                ]),
            $this->getNestedResourceAsJson(
                $resource, 'vacations.institution_vacations', [
                    'id',
                    'start_date',
                    'end_date',
                ]),
        );

        if (filled($vacations)) {
            usort($vacations, fn ($v1, $v2) => $v1['start_date'] <=> $v2['start_date']);
        }

        $obj->vacations = $vacations;
        $obj->deleted_at = $resource['deleted_at'];
        $obj->synced_at = Carbon::now();

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

        $this->syncVacations($obj, $vacations);
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

    /**
     * Replace vacation-type vendor_calendar_entries for this user's vendor.
     *
     * @param  array<array{start_date: string, end_date: string}>  $vacationsFromJsonb
     */
    private function syncVacations(InstitutionUser $user, array $vacationsFromJsonb): void
    {
        $vendorId = Vendor::where('institution_user_id', $user->id)->value('id');

        if ($vendorId === null) {
            return;
        }

        VendorCalendarEntry::where('vendor_id', $vendorId)->vacationsOnly()->forceDelete();

        $now = Carbon::now()->toDateTimeString();

        $rows = collect($vacationsFromJsonb)->map(fn ($v) => [
            'id' => Str::uuid()->toString(),
            'vendor_id' => $vendorId,
            'start_at' => Carbon::parse($v['start_date'])->startOfDay()->utc(),
            'end_at' => Carbon::parse($v['end_date'])->addDay()->startOfDay()->utc(),
            'created_at' => $now,
            'updated_at' => $now,
        ])->toArray();

        if (! empty($rows)) {
            VendorCalendarEntry::insert($rows);
        }
    }

    private function getBaseModel(): InstitutionUser
    {
        return InstitutionUser::getModel();
    }

    private function getNestedResourceAsJson(array $resource, string $key, array $attributes): array
    {
        if (empty($data = data_get($resource, $key))) {
            return [];
        }

        if (Arr::isAssoc($data)) {
            return Arr::only($data, $attributes);
        }

        return collect($data)->map(
            fn ($subResource) => Arr::only($subResource, $attributes)
        )->toArray();
    }
}
