<?php

namespace App\Services\Calendar;

use App\Enums\SkillCode;
use App\Models\CalendarSetting;
use App\Models\JobDefinition;
use App\Models\ProjectTypeConfig;
use App\Models\Skill;
use RuntimeException;

class CalendarSkillResolver
{
    /**
     * @var array<string, string>
     */
    private array $cache = [];

    public function getDefaultCalendarSkillId(string $institutionId): string
    {
        if (isset($this->cache[$institutionId])) {
            return $this->cache[$institutionId];
        }

        $calendarSetting = CalendarSetting::query()
            ->where('institution_id', $institutionId)
            ->first();

        if (! $calendarSetting) {
            $this->cache[$institutionId] = $this->getDefaultSkill()->id;
            return $this->cache[$institutionId];
        }

        $projectTypeConfig = ProjectTypeConfig::query()
            ->where('type_classifier_value_id', $calendarSetting->default_project_type_id)
            ->first();

        if (! $projectTypeConfig) {
            $this->cache[$institutionId] = $this->getDefaultSkill()->id;
            return $this->cache[$institutionId];
        }

        $jobDefinition = JobDefinition::query()
            ->where('project_type_config_id', $projectTypeConfig->id)
            ->whereNotNull('skill_id')
            ->orderBy('sequence')
            ->first();

        if (! $jobDefinition || ! $jobDefinition->skill_id) {
            $this->cache[$institutionId] = $this->getDefaultSkill()->id;
            return $this->cache[$institutionId];
        }

        return $this->cache[$institutionId] = $jobDefinition->skill_id;
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

