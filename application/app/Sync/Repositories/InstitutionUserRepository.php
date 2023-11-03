<?php

namespace App\Sync\Repositories;

use App\Models\CachedEntities\InstitutionUser;
use Arr;
use Carbon\Carbon;
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
        $obj->deleted_at = $resource['deleted_at'];
        $obj->synced_at = Carbon::now();

        $obj->save();
    }

    public function delete(string $id): void
    {
        $obj = $this->getBaseModel()->find($id);
        $obj->delete();
    }

    public function deleteNotSynced(): void
    {
        $this->getBaseModel()->whereNull('synced_at')
            ->delete();
    }

    public function cleanupLastSyncDateTime(): void
    {
        $this->getBaseModel()->update(['synced_at' => null]);
    }

    private function getBaseModel(): InstitutionUser
    {
        return InstitutionUser::getModel()->setConnection(config('pgsql-connection.sync.name'));
    }

    private function getNestedResourceAsJson(array $resource, string $key, array $attributes): array
    {
        if (empty($resource[$key])) {
            return [];
        }

        if (Arr::isAssoc($resource[$key])) {
            return Arr::only($resource[$key], $attributes);
        }

        return collect($resource[$key])->each(
            fn ($subResource) => Arr::only($subResource, $attributes)
        )->toArray();
    }
}
