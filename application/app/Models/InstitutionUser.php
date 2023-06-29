<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasOne;
use SyncTools\Traits\HasCachedEntityDbSchema;
use SyncTools\Traits\HasCachedEntityFactory;

class InstitutionUser extends Model
{
    use HasUuids;
    use HasCachedEntityDbSchema;
    use HasCachedEntityFactory;

//    protected $connection = 'pgsql_sync';
    protected $table = 'cached_institution_users';

    public function vendor(): HasOne
    {
        return $this->hasOne(Vendor::class);
    }
}
