<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentCatToolJob extends Model
{
    use HasUuids, HasFactory;

    protected $table = 'assignment_cat_tool_jobs';

    protected $fillable = [
        'cat_tool_job_id',
        'assignment_id',
    ];

    public function catToolJob(): BelongsTo
    {
        return $this->belongsTo(CatToolJob::class, 'cat_tool_job_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class, 'assignment_id');
    }
}
