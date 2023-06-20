<?php

namespace App\Sync\Repositories;

use Arr;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use SyncTools\Repositories\CachedEntityRepositoryInterface;

class InstitutionUserRepository implements CachedEntityRepositoryInterface
{
    public function save(array $resource): void
    {
        $this->getBaseQuery()->updateOrInsert(['id' => $resource['id']], [
            'email' => $resource['email'],
            'phone' => $resource['phone'],
            'archived_at' => $resource['archived_at'],
            'deactivation_date' => $resource['deactivation_date'],
            'user' => $this->getNestedResourceAsJson(
                $resource, 'user', [
                    'id',
                    'personal_identification_code',
                    'forename',
                    'surname',
                ]),
            'institution' => $this->getNestedResourceAsJson(
                $resource, 'institution', [
                    'id',
                    'name',
                    'short_name',
                    'phone',
                    'email',
                    'logo_url',
                ]),
            'department' => $this->getNestedResourceAsJson(
                $resource, 'department', [
                    'id',
                    'institution_id',
                    'name',
                ]),
            'roles' => $this->getNestedResourceAsJson(
                $resource, 'roles', [
                    'id',
                    'name',
                    'institution_id',
                    'privileges',
                ]),
            'synced_at' => Carbon::now()->toISOString(),
            'deleted_at' => $resource['deleted_at'],
        ]);
    }

    public function delete(string $id): void
    {
        $this->getBaseQuery()->delete($id);
    }

    public function deleteNotSynced(): void
    {
        $this->getBaseQuery()->whereNull('synced_at')
            ->delete();
    }

    public function cleanupLastSyncDateTime(): void
    {
        $this->getBaseQuery()->update(['synced_at' => null]);
    }

    private function getBaseQuery(): Builder
    {
        return DB::connection(config('pgsql-connection.sync.name'))->table('cached_institution_users');
    }

    private function getNestedResourceAsJson(array $resource, string $key, array $attributes): string
    {
        if (empty($resource[$key])) {
            return json_encode([]);
        }

        if (Arr::isAssoc($resource[$key])) {
            return json_encode(Arr::only($resource[$key], $attributes));
        }

        return json_encode(
            collect($resource[$key])->each(
                fn ($subResource) => Arr::only($subResource, $attributes)
            )->toArray()
        );
    }
}
