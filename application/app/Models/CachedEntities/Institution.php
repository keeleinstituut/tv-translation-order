<?php

namespace App\Models\CachedEntities;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use SyncTools\Traits\HasCachedEntityDbSchema;
use SyncTools\Traits\HasCachedEntityFactory;

class Institution extends Model
{
    use HasCachedEntityFactory, HasCachedEntityDbSchema, HasUuids, SoftDeletes;

    protected $table = 'cached_institutions';

    public $timestamps = false;
}
