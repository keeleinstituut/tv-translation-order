<?php

namespace App\Sync\Repositories;

use App\Models\CachedEntities\ClassifierValue;
use Carbon\Carbon;
use SyncTools\Repositories\CachedEntityRepositoryInterface;

class ClassifierValueRepository implements CachedEntityRepositoryInterface
{
    public function save(array $resource): void
    {
        $obj = $this->getBaseModel()->withTrashed()->find($resource['id']);

        if (!$obj) {
            $obj = $this->getBaseModel();
            $obj->id = $resource['id'];
        }

        $obj->name = $resource['name'];
        $obj->value = $resource['value'];
        $obj->type = $resource['type'];
        $obj->meta = $resource['meta'] ?? [];
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

    private function getBaseModel(): ClassifierValue
    {
        return ClassifierValue::getModel()->setConnection(config('pgsql-connection.sync.name'));
    }
}
