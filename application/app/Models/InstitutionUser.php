<?php

namespace App\Models;

use App\Traits\HasReadonlyAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InstitutionUser extends Model
{
    // use HasReadonlyAccess;
    use HasUuids;
    use HasFactory;

    protected $table = 'cached_institution_users';


    public function vendor(): HasOne
    {
        return $this->hasOne(Vendor::class);
    }
}
