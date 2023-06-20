<?php

namespace App\Sync\Repositories;

use Arr;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
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
            'user' => json_encode(
                Arr::only($resource['user'], [
                    'id',
                    'personal_identification_code',
                    'forename',
                    'surname'
                ])
            ),
            'institution' => json_encode(
                Arr::only($resource['institution'], [
                    'id',
                    'name',
                    'short_name',
                    'phone',
                    'email',
                    'logo_url'
                ])
            ),
            'department' => json_encode(
                Arr::only($resource['department'], [
                    'id',
                    'institution_id',
                    'name'
                ])
            ),
            'roles' => json_encode(
                collect($resource['roles'])->each(fn($roleResource) => Arr::only($roleResource, [
                    'id',
                    'name',
                    'institution_id',
                    'privileges'
                ]))->toArray()
            ),
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
}
