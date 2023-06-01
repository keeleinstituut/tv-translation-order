<?php

namespace App\Sync\Repositories;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use SyncTools\Repositories\CachedEntityRepositoryInterface;

class ClassifierValueRepository implements CachedEntityRepositoryInterface
{
    public function save(array $resource): void
    {
        $this->getBaseQuery()->updateOrInsert(['id' => $resource['id']], [
            'name' => $resource['name'],
            'value' => $resource['value'],
            'type' => $resource['type'],
            'meta' => is_array($resource['meta']) ? json_encode($resource['meta']) : $resource['meta'],
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
        $this->getBaseQuery()->where('synced_at', '<=', $syncStartTime->toIsoString())
            ->delete();
    }

    private function getBaseQuery(): Builder
    {
        return DB::connection(config('pgsql-connection.sync.name'))->table('cached_classifier_values');
    }
}
