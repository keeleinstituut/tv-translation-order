<?php

namespace App\Models\CachedEntities;

use App\Models\Sequence;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use SyncTools\Traits\IsCachedEntity;

class Institution extends Model
{
    use IsCachedEntity, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'cached_institutions';

    public $timestamps = false;

    public function institutionProjectSequence()
    {
        return $this->morphOne(Sequence::class, 'sequenceable')
            ->where('name', Sequence::INSTITUTION_PROJECT_SEQ);
    }
}
