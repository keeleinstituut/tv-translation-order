<?php

namespace App\Sync\Repositories;

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
            'institution_id' => $resource['institution_id'],
            'department_id' => $resource['department']['id'] ?? null,
            'user_id' => $resource['user']['id'],
            'forename' => $resource['user']['forename'],
            'surname' => $resource['user']['surname'],
            'personal_identification_code' => $resource['user']['personal_identification_code'],
            'status' => $resource['status'],
            'email' => $resource['email'],
            'phone' => $resource['phone'],
            'created_at' => $resource['created_at'],
            'updated_at' => $resource['updated_at'],
            'synced_at' => new Expression('NOW()'),
            'deleted_at' => $resource['deleted_at'],
        ]);
    }

    public function delete(string $id): void
    {
        $this->getBaseQuery()->delete($id);
    }

    public function getLastSyncDateTime(): ?string
    {
        return $this->getBaseQuery()->max('synced_at');
    }

    public function deleteNotSynced(Carbon $syncStartTime): void
    {
        $this->getBaseQuery()->where('synced_at', '<', $syncStartTime->toIsoString())
            ->delete();
    }

    private function getBaseQuery(): Builder
    {
        return DB::connection(config('pgsql-connection.sync.name'))->table('cached_institution_users');
    }
}
