<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CamundaTask extends Model
{
    use HasUuids;
    use HasFactory;

    public function assignment()
    {
        return $this->belongsTo(Assignment::class, 'var_assignment_id');
    }

    public function project()
    {
        return $this->belongsTo(project::class, 'var_project_id');
    }
}
