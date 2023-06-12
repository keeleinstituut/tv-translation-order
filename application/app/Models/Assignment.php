<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    use HasUuids;
    use HasFactory;

    public function subProject() {
        return $this->belongsTo(SubProject::class);
    }

    public function candidates() {
        return $this->hasMany(Candidate::class);
    }

    public function assignee() {
        return $this->belongsTo(Vendor::class, 'assigned_vendor_id');
    }
}
