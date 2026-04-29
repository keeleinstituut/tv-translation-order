<?php

namespace Database\Seeders;

use App\Enums\ClassifierValueType;
use App\Models\CachedEntities\ClassifierValue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CalendarSettingsSeeder extends Seeder
{
    /**
     * Seed default calendar_settings for every existing institution.
     * Safe to run multiple times — skips institutions that already have a row.
     */
    public function run(): void
    {
        $this->call(ProjectTypeClassifierValueSeeder::class);

        $institutionIds = DB::table('cached_institutions')
            ->whereNull('deleted_at')
            ->pluck('id');

        $existingIds = DB::table('calendar_settings')
            ->whereIn('institution_id', $institutionIds)
            ->pluck('institution_id')
            ->flip();

        $now = now()->toDateTimeString();

        $rows = $institutionIds
            ->reject(fn (string $id) => $existingIds->has($id))
            ->map(fn (string $id) => [
                'id' => Str::orderedUuid()->toString(),
                'institution_id' => $id,
                'reaction_time_minutes' => 30,
                'buffer_before_minutes' => 0,
                'buffer_after_minutes' => 0,
                'default_project_type_id' => $this->getCalendarSupportedProjectTypes()->random(),
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->toArray();

        if (! empty($rows)) {
            DB::table('calendar_settings')->insert($rows);
        }
    }


    private function getCalendarSupportedProjectTypes(): Collection
    {
        return ClassifierValue::query()->where('type', ClassifierValueType::ProjectType->value)
            ->whereIn('value', ['ORAL_TRANSLATION', 'SYNCHRONOUS_TRANSLATION', 'POST_TRANSLATION', 'SIGN_LANGUAGE'])
            ->pluck('id');
    }
}
