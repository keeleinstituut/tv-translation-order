<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use SyncTools\Traits\IsCachedEntity;

class ClassifierValue extends Model
{
    use HasUuids;
    use IsCachedEntity;
    use HasFactory;

//    protected $connection = 'pgsql_sync';
    protected $table = 'cached_classifier_values';
}
