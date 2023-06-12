<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Sequence extends Model
{
    use HasFactory;
    use HasUuids;

    public const INSTITUTION_PROJECT_SEQ = 'INSTITUTION_PROJECT_SEQUENCE';
    public const PROJECT_SUBPROJECT_SEQ = 'PROJECT_SUBPROJECT_SEQ';

    public function sequenceable()
    {
        return $this->morphTo();
    }

    public function incrementCurrentValue() {
        return DB::transaction(function () {
            $value = $this->current_value;
            $this->current_value += 1;
            $this->save();
            return $value;
        });
    }
}
