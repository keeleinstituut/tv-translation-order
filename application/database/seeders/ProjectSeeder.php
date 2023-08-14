<?php

namespace Database\Seeders;

use App\Enums\ClassifierValueType;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\Candidate;
use App\Models\Project;
use App\Models\SubProject;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

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
     */
    public function run(): void
    {
        $projectTypes = ClassifierValue::where('type', ClassifierValueType::ProjectType)->get();
        $languages = ClassifierValue::where('type', ClassifierValueType::Language)->get();

        $projects = Project::factory()
            ->count(10)
            ->state(fn ($attrs) => [
                'type_classifier_value_id' => fake()->randomElement($projectTypes),
                'workflow_template_id' => 'Sample-project',
            ])
            ->create();

        $projects->each($this->addRandomFilesToProject(...));
        $projects->each(function (Project $project) use ($languages) {
            $destinationLanguagesCount = fake()->numberBetween(1, 4);
            $languagesSelection = collect(fake()->randomElements($languages, $destinationLanguagesCount + 1));
            $sourceLanguage = $languagesSelection->get(0);
            $destinationLanguages = $languagesSelection->skip(1);
            $project->initSubProjects($sourceLanguage, $destinationLanguages);
            $project->workflow()->startProcessInstance();
        });

        $projects->pluck('subProjects')->flatten()->each(function (SubProject $subProject) {
            if (fake()->randomElement([0, 0, 1]) == 1) {
                $subProject->cat()->createProject();
            }
        });

        Assignment::all()->each($this->setAssigneeOrCandidates(...));
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
            ->reject(fn ($filename) => $filename == '.' || $filename == '..')
            ->map(function ($filename) {
                return self::SAMPLE_FILES_DIR.'/'.$filename;
            });
    }

    private function setAssigneeOrCandidates(Assignment $assignment)
    {
        $setAssignee = fake()->randomElement([false, false, true]);

        if ($setAssignee) {
            $assignment->assigned_vendor_id = Vendor::factory()->create()->id;
            $assignment->save();

            $candidate = new Candidate();
            $candidate->vendor_id = $assignment->assigned_vendor_id;
            $candidate->assignment_id = $assignment->id;
            $candidate->save();
        } else {
            $count = fake()->numberBetween(0, 3);
            collect()->times($count)->each(function () use ($assignment) {
                $candidate = new Candidate();
                $candidate->vendor_id = Vendor::factory()->create()->id;
                $candidate->assignment_id = $assignment->id;
                $candidate->save();
            });
        }
    }
}
