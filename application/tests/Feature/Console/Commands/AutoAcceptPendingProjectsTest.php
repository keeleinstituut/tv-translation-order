<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\ProjectStatus;
use App\Models\CachedEntities\Institution;
use App\Models\Project;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AutoAcceptPendingProjectsTest extends TestCase
{
    private string $camundaBaseUrl;

    public function setUp(): void
    {
        parent::setUp();
        $this->camundaBaseUrl = rtrim(env('CAMUNDA_API_URL', 'http://process-definition'), '/');

        // Reset stubs so these take priority over the base TestCase setUp stubs.
        // Http::fake() appends stubs; setUp's broad '/*' pattern would otherwise
        // match before the test-specific ones, returning the wrong response.
        Http::swap(new Factory());
    }

    public function test_auto_accepts_project_warned_over_a_day_ago(): void
    {
        $this->fakeClientReviewThenEmptyWorkflow();
        $project = $this->createSubmittedProject(Carbon::now()->subDays(2));

        $this->artisan('app:auto-accept-pending-projects')->assertSuccessful();

        $project->refresh();
        $this->assertEquals(ProjectStatus::Accepted, $project->status);
        $this->assertNotNull($project->accepted_at);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/task/task-1/complete'));
    }

    public function test_skips_project_warned_less_than_a_day_ago(): void
    {
        $project = $this->createSubmittedProject(Carbon::now()->subHours(12));

        $this->artisan('app:auto-accept-pending-projects')->assertSuccessful();

        $this->assertEquals(ProjectStatus::SubmittedToClient, $project->refresh()->status);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/complete'));
    }

    public function test_skips_project_without_open_client_review_task(): void
    {
        $this->fakeEmptyWorkflow();
        $project = $this->createSubmittedProject(Carbon::now()->subDays(2));

        $this->artisan('app:auto-accept-pending-projects')->assertSuccessful();

        $this->assertEquals(ProjectStatus::SubmittedToClient, $project->refresh()->status);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/complete'));
    }

    private function createSubmittedProject(Carbon $notifiedAt): Project
    {
        return Project::factory()->create([
            'ext_id' => fake()->uuid(),
            'institution_id' => Institution::factory(),
            'status' => ProjectStatus::SubmittedToClient,
            'submitted_to_client_review_at' => Carbon::now()->subDays(10),
            'auto_acceptance_notification_sent_at' => $notifiedAt,
        ]);
    }

    /**
     * First workflow lookup returns an open CLIENT_REVIEW task; after the task is completed
     * the second lookup (performed by TrackProjectStatus) returns no tasks, so the project is accepted.
     */
    private function fakeClientReviewThenEmptyWorkflow(): void
    {
        $listCall = 0;

        Http::fake([
            "$this->camundaBaseUrl/task/count" => Http::sequence()->push(['count' => 1])->push(['count' => 0]),
            "$this->camundaBaseUrl/variable-instance*" => Http::sequence()
                ->push([['executionId' => 'exec-1', 'name' => 'task_type', 'value' => 'CLIENT_REVIEW']])
                ->push([]),
            "$this->camundaBaseUrl/*" => function ($request) use (&$listCall) {
                if (str_contains($request->url(), '/complete')) {
                    return Http::response([]);
                }

                $listCall++;

                return Http::response(
                    $listCall === 1 ? [['id' => 'task-1', 'executionId' => 'exec-1']] : []
                );
            },
        ]);
    }

    private function fakeEmptyWorkflow(): void
    {
        Http::fake([
            "$this->camundaBaseUrl/task/count" => Http::response(['count' => 0]),
            "$this->camundaBaseUrl/*" => Http::response([]),
        ]);
    }
}
