<?php

namespace App\Repositories;

use Amqp\Repositories\CachedEntityRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ClassifierValueRepository implements CachedEntityRepositoryInterface
{
    public function save(array $resource): void
    {
        $this->getBaseQuery()->updateOrInsert(['id' => $resource['id']], [
            'name' => $resource['name'],
            'value' => $resource['value'],
            'type' => $resource['type'],
            'meta' => is_array($resource['meta']) ? json_encode($resource['meta']) : $resource['meta'],
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
        return DB::connection('entity-cache-pgsql')->table('cached_classifier_values');
    }
}
