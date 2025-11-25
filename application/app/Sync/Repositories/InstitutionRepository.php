<?php

namespace App\Sync\Repositories;

use App\Models\CachedEntities\Institution;
use Carbon\Carbon;
use SyncTools\Repositories\CachedEntityRepositoryInterface;

class InstitutionRepository implements CachedEntityRepositoryInterface
{
    public function save(array $resource): void
    {
        $obj = $this->getBaseModel()->withTrashed()->find($resource['id']);

        if (! $obj) {
            $obj = $this->getBaseModel();
            $obj->id = $resource['id'];
        }

        $obj->name = $resource['name'];
        $obj->short_name = $resource['short_name'];
        $obj->phone = $resource['phone'];
        $obj->email = $resource['email'];
        $obj->logo_url = $resource['logo_url'];
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

    private function getBaseModel(): Institution
    {
        return Institution::getModel()->setConnection(config('pgsql-connection.sync.name'));
    }
}
