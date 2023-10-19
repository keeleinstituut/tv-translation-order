<?php

namespace App\Sync\Repositories;

use App\Models\CachedEntities\Institution;
use Carbon\Carbon;
use SyncTools\Repositories\CachedEntityRepositoryInterface;

class InstitutionRepository implements CachedEntityRepositoryInterface
{
    public function save(array $resource): void
    {
        $obj = $this->getBaseQuery()->withTrashed()->find($resource['id']);

        if (!$obj) {
            $obj = new Institution();
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
        $obj = $this->getBaseQuery()->find($id);
        $obj->delete();
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

    private function getBaseQuery(): Institution
    {
        return Institution::getModel()->setConnection(config('pgsql-connection.sync.name'));
    }
}
