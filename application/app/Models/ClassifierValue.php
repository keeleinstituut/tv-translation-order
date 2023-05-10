<?php

namespace App\Models;

use App\Traits\HasReadonlyAccess;
use Illuminate\Database\Eloquent\Model;

class ClassifierValue extends Model
{
    use HasReadonlyAccess;

    protected $table = 'cached_classifier_values';
}
