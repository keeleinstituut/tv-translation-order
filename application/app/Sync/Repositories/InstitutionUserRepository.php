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

        $userVacations = collect($this->getNestedResourceAsJson(
            $resource, 'vacations.institution_user_vacations', ['id', 'start_date', 'end_date']
        ))->map(fn (array $v) => [
            ...$v,
            'institution_user_vacation_id' => $v['id'],
            'institution_vacation_id' => null,
        ]);

        $institutionVacations = collect($this->getNestedResourceAsJson(
            $resource, 'vacations.institution_vacations', ['id', 'start_date', 'end_date']
        ))->map(fn (array $v) => [
            ...$v,
            'institution_user_vacation_id' => null,
            'institution_vacation_id' => $v['id'],
        ]);

        $vacations = $userVacations->merge($institutionVacations)
            ->sortBy('start_date')
            ->values()
            ->toArray();

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
     * Upsert vacation-type vendor_calendar_entries for this user's vendor.
     *
     * Only future-starting VCE rows whose source ID is missing from the
     * incoming set are deleted; past rows are never touched.
     *
     * @param  array<array{start_date: string, end_date: string, institution_user_vacation_id: ?string, institution_vacation_id: ?string}>  $vacationsFromJsonb
     */
    private function syncVacations(InstitutionUser $user, array $vacationsFromJsonb): void
    {
        $vendorId = Vendor::where('institution_user_id', $user->id)->value('id');

        if ($vendorId === null) {
            return;
        }

        $today = Carbon::today()->utc();
        $now = Carbon::now()->toDateTimeString();

        $userVacationIds = [];
        $instVacationIds = [];
        $userRows = [];
        $instRows = [];

        foreach ($vacationsFromJsonb as $v) {
            $sourceUserId = $v['institution_user_vacation_id'] ?? null;
            $sourceInstId = $v['institution_vacation_id'] ?? null;

            $row = [
                'id' => Str::uuid()->toString(),
                'vendor_id' => $vendorId,
                'start_at' => Carbon::parse($v['start_date'])->startOfDay()->utc(),
                'end_at' => Carbon::parse($v['end_date'])->addDay()->startOfDay()->utc(),
                'institution_user_vacation_id' => $sourceUserId,
                'institution_vacation_id' => $sourceInstId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($sourceUserId !== null) {
                $userVacationIds[] = $sourceUserId;
                $userRows[] = $row;
            }

            if ($sourceInstId !== null) {
                $instVacationIds[] = $sourceInstId;
                $instRows[] = $row;
            }
        }

        // Delete future-starting VCE rows whose source ID is no longer in incoming set
        VendorCalendarEntry::where('vendor_id', $vendorId)
            ->where('start_at', '>=', $today)
            ->whereNotNull('institution_user_vacation_id')
            ->whereNotIn('institution_user_vacation_id', $userVacationIds)
            ->forceDelete();

        VendorCalendarEntry::where('vendor_id', $vendorId)
            ->where('start_at', '>=', $today)
            ->whereNotNull('institution_vacation_id')
            ->whereNotIn('institution_vacation_id', $instVacationIds)
            ->forceDelete();

        // Upsert — updateOrCreate is needed because the unique indexes are partial
        // (WHERE ... IS NOT NULL AND deleted_at IS NULL), which Laravel's upsert() cannot target.
        foreach ($userRows as $row) {
            VendorCalendarEntry::updateOrCreate(
                [
                    'vendor_id' => $row['vendor_id'],
                    'institution_user_vacation_id' => $row['institution_user_vacation_id'],
                ],
                [
                    'start_at' => $row['start_at'],
                    'end_at' => $row['end_at'],
                ]
            );
        }

        foreach ($instRows as $row) {
            VendorCalendarEntry::updateOrCreate(
                [
                    'vendor_id' => $row['vendor_id'],
                    'institution_vacation_id' => $row['institution_vacation_id'],
                ],
                [
                    'start_at' => $row['start_at'],
                    'end_at' => $row['end_at'],
                ]
            );
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
