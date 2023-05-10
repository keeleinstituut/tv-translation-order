<?php

namespace App\Repositories;

use Amqp\Repositories\CachedEntityRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InstitutionRepository implements CachedEntityRepositoryInterface
{
    public function save(array $resource): void
    {
        $this->getBaseQuery()->updateOrInsert(['id' => $resource['id']], [
            'id' => $resource['id'],
            'name' => $resource['name'],
            'logo_url' => $resource['logo_url'],
            'created_at' => $resource['created_at'],
            'updated_at' => $resource['updated_at'],
            'synced_at' => new Expression("NOW()")
        ]);
    }

    public function delete(string $id): void
    {
        $this->getBaseQuery()->delete($id);
    }

    public function deleteNotSynced(Carbon $syncStartTime): void
    {
        $this->getBaseQuery()->where('synced_at', '<', $syncStartTime->toIsoString())
            ->delete();
    }

    private function getBaseQuery(): Builder
    {
        return DB::connection('entity-cache-pgsql')->table('cached_institutions');
    }
}
