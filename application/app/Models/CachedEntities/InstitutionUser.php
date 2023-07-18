<?php

namespace App\Models\CachedEntities;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use SyncTools\Traits\IsCachedEntity;

class InstitutionUser extends Model
{
    use IsCachedEntity, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'cached_institution_users';

    public $timestamps = false;

    protected $casts = [
        'user' => 'array',
        'institution' => 'array',
        'department' => 'array',
        'roles' => 'array',
    ];
}
