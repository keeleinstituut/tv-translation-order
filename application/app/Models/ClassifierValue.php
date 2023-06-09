<?php

namespace App\Models;

use App\Traits\HasReadonlyAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ClassifierValue extends Model
{
    // use HasReadonlyAccess;
    use HasUuids;
    use HasFactory;

    protected $table = 'cached_classifier_values';
}
