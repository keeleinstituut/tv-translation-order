<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use SyncTools\Traits\HasCachedEntityFactory;

class InstitutionUser extends Model
{
    use HasCachedEntityFactory, HasUuids, SoftDeletes;

    protected $table = 'entity_cache.cached_institution_users';
}
