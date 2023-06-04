<?php

namespace App\Sync\Repositories;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use SyncTools\Repositories\CachedEntityRepositoryInterface;

class InstitutionRepository implements CachedEntityRepositoryInterface
{
    public function save(array $resource): void
    {
        $this->getBaseQuery()->updateOrInsert(['id' => $resource['id']], [
            'id' => $resource['id'],
            'name' => $resource['name'],
            'short_name' => $resource['short_name'],
            'phone' => $resource['phone'],
            'email' => $resource['email'],
            'logo_url' => $resource['logo_url'],
            'created_at' => $resource['created_at'],
            'updated_at' => $resource['updated_at'],
            'deleted_at' => $resource['deleted_at'],
            'synced_at' => new Expression('NOW()'),
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
        return DB::connection(config('pgsql-connection.sync.name'))->table('cached_institutions');
    }
}
