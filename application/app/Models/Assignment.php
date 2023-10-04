<?php

namespace App\Models;

use Database\Factories\AssignmentFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * App\Models\Assignment
 *
 * @property string|null $id
 * @property string|null $sub_project_id
 * @property string|null $assigned_vendor_id
 * @property string|null $deadline_at
 * @property string|null $comments
 * @property string|null $assignee_comments
 * @property string|null $feature
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Vendor|null $assignee
 * @property-read Collection<int, Candidate> $candidates
 * @property-read int|null $candidates_count
 * @property-read SubProject $subProject
 *
 * @method static AssignmentFactory factory($count = null, $state = [])
 * @method static Builder|Assignment newModelQuery()
 * @method static Builder|Assignment newQuery()
 * @method static Builder|Assignment query()
 * @method static Builder|Assignment whereAssignedVendorId($value)
 * @method static Builder|Assignment whereAssigneeComments($value)
 * @method static Builder|Assignment whereComments($value)
 * @method static Builder|Assignment whereCreatedAt($value)
 * @method static Builder|Assignment whereDeadlineAt($value)
 * @method static Builder|Assignment whereFeature($value)
 * @method static Builder|Assignment whereId($value)
 * @method static Builder|Assignment whereSubProjectId($value)
 * @method static Builder|Assignment whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class Assignment extends Model
{
    use HasUuids;
    use HasFactory;

    public function subProject()
    {
        return $this->belongsTo(SubProject::class);
    }

    public function candidates()
    {
        return $this->hasMany(Candidate::class);
    }

    public function assignee()
    {
        return $this->belongsTo(Vendor::class, 'assigned_vendor_id');
    }
}
