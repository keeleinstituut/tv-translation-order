<?php

namespace Database\Seeders;

use App\Enums\ClassifierValueType;
use App\Enums\JobKey;
use App\Enums\PrivilegeKey;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Candidate;
use App\Models\Project;
use App\Models\SubProject;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Throwable;

class ProjectSeeder extends Seeder
{
    const SAMPLE_FILES_DIR = './database/seeders/sample-files/en';

    private $sampleFiles;

    public function __construct()
    {
        $this->sampleFiles = self::getSampleFiles();
    }

    /**
     * Run the database seeds.
     *
     * @throws Throwable
     */
    public function run(): void
    {
        $client = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::CreateProject);
        $projectTypes = ClassifierValue::where('type', ClassifierValueType::ProjectType)->get();
        $languages = ClassifierValue::where('type', ClassifierValueType::Language)->get();

        $projects = Project::factory()
            ->count(1)
            ->state(fn($attrs) => [
                'type_classifier_value_id' => fake()->randomElement($projectTypes)->id,
                'workflow_template_id' => 'project-workflow',
                'client_institution_user_id' => $client->id,
                'institution_id' => $client->institution['id'],
            ])->create();

        $projects->each($this->addRandomFilesToProject(...));
        $projects->each(function (Project $project) use ($languages) {
            $destinationLanguagesCount = 1;
            $languagesSelection = collect(fake()->randomElements($languages, $destinationLanguagesCount + 1));
            $sourceLanguage = $languagesSelection->get(0);
            $destinationLanguages = $languagesSelection->skip(1);
            $project->initSubProjects($sourceLanguage, $destinationLanguages);

            $project->refresh();

            $project->subProjects->each(function (SubProject $subProject) {
                $subProject->assignments->each($this->setAssigneeOrCandidates(...));
            });
            $project->workflow()->start();
        });
    }

    private function addRandomFilesToProject(Project $project)
    {
        $this->randomTimes(function () use ($project) {
            $project
                ->addMedia($this->getRandomSampleFile())
                ->preservingOriginal()
                ->toMediaCollection(Project::SOURCE_FILES_COLLECTION);
        });
    }

    private function getRandomSampleFile()
    {
        return fake()->randomElement($this->sampleFiles);
    }

    private function randomTimes(callable $callable): void
    {
        collect()->times(fake()->numberBetween(1, 10))->each($callable);
    }

    private static function getSampleFiles()
    {
        return collect(scandir(self::SAMPLE_FILES_DIR))
            ->reject(fn($filename) => $filename == '.' || $filename == '..')
            ->map(function ($filename) {
                return self::SAMPLE_FILES_DIR . '/' . $filename;
            });
    }

    private function setAssigneeOrCandidates(Assignment $assignment): void
    {
        $setAssignee = $assignment->jobDefinition->job_key === JobKey::JOB_OVERVIEW;

        if ($setAssignee) {
            $assignment->assigned_vendor_id = Vendor::factory()->create()->id;
            $assignment->saveOrFail();

            $candidate = new Candidate();
            $candidate->vendor_id = $assignment->assigned_vendor_id;
            $candidate->assignment_id = $assignment->id;
            $candidate->saveOrFail();
        } else {
            $count = fake()->numberBetween(1, 4);
            collect()->times($count)->each(function () use ($assignment) {
                $candidate = new Candidate();
                $candidate->vendor_id = Vendor::factory()->create()->id;
                $candidate->assignment_id = $assignment->id;
                $candidate->saveOrFail();
            });
        }
    }
}
