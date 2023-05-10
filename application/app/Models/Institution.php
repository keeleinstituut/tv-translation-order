<?php

namespace App\Models;

use App\Traits\HasReadonlyAccess;
use Illuminate\Database\Eloquent\Model;

class Institution extends Model
{
    use HasReadonlyAccess;

    protected $table = 'cached_institutions';
}
