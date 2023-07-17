<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasOne;
use SyncTools\Traits\IsCachedEntity;

class InstitutionUser extends Model
{
    use HasUuids;
    use IsCachedEntity;
    use HasFactory;

    protected $table = 'cached_institution_users';

    public function vendor(): HasOne
    {
        return $this->hasOne(Vendor::class);
    }
}
