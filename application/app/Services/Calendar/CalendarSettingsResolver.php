<?php

namespace App\Services\Calendar;

use App\Enums\ServiceType;
use App\Enums\SkillCode;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\InstitutionSetting;
use App\Models\JobDefinition;
use App\Models\Project;
use App\Models\ProjectTypeConfig;
use App\Models\Skill;
use Illuminate\Support\Carbon;
use RuntimeException;

class CalendarSettingsResolver
{
    private ?string $defaultSkillId = null;

    public function getDefaultCalendarSkillId(): string
    {
        if ($this->defaultSkillId !== null) {
            return $this->defaultSkillId;
        }

        $projectTypeConfig = ClassifierValue::getCalendarProjectType()->projectTypeConfig;

        if (! $projectTypeConfig) {
            return $this->defaultSkillId = $this->getDefaultSkill()->id;
        }

        $jobDefinition = JobDefinition::query()
            ->where('project_type_config_id', $projectTypeConfig->id)
            ->whereNotNull('skill_id')
            ->orderBy('sequence')
            ->first();

        if (! $jobDefinition || ! $jobDefinition->skill_id) {
            return $this->defaultSkillId = $this->getDefaultSkill()->id;
        }

        return $this->defaultSkillId = $jobDefinition->skill_id;
    }

    public function resolveTimeSlot(
        Carbon       $startAt,
        Carbon       $endAt,
        ?ServiceType $serviceType,
        string       $institutionId,
    ): TimeSlot
    {
        if ($serviceType !== ServiceType::OnSite) {
            return TimeSlot::forEvent($startAt, $endAt);
        }

        $setting = InstitutionSetting::query()
            ->where('institution_id', $institutionId)
            ->first();

        $before = $setting?->buffer_before_minutes ?? 30;
        $after = $setting?->buffer_after_minutes ?? 30;

        if ($before === 0 && $after === 0) {
            return TimeSlot::forEvent($startAt, $endAt);
        }

        return new TimeSlot(
            $startAt,
            $endAt,
            $startAt->copy()->subMinutes($before),
            $endAt->copy()->addMinutes($after),
        );
    }

    public function resolveTimeSlotForProject(Project $project): TimeSlot
    {
        return $this->resolveTimeSlot(
            $project->event_start_at,
            $project->event_end_at,
            $project->service_type,
            $project->institution_id,
        );
    }

    private function getDefaultSkill(): Skill
    {
        $skill = Skill::query()->where('code', SkillCode::OralInterpretation)->first();
        if (! $skill) {
            throw new RuntimeException('Failed to resolve default skill for calendar');
        }

        return $skill;
    }
}

