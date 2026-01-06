<?php

namespace App\Sync\Repositories;

use App\Models\CachedEntities\InstitutionUser;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use SyncTools\Repositories\CachedEntityRepositoryInterface;

class InstitutionUserRepository implements CachedEntityRepositoryInterface
{
    public function save(array $resource): void
    {
        $obj = $this->getBaseModel()->withTrashed()->find($resource['id']);

        if (!$obj) {
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
                'end_date'
            ]),
            $this->getNestedResourceAsJson(
                $resource, 'vacations.institution_vacations', [
                'id',
                'start_date',
                'end_date'
            ]),
        );

        if (filled($vacations)) {
            usort($vacations, fn($v1, $v2) => $v1['start_date'] <=> $v2['start_date']);
        }

        $obj->vacations = $vacations;
        $obj->deleted_at = $resource['deleted_at'];
        $obj->synced_at = Carbon::now();

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
            fn($subResource) => Arr::only($subResource, $attributes)
        )->toArray();
    }
}
