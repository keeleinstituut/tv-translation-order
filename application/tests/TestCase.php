<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $exchanges = [
            [
                'exchange' => 'classifier-value',
                'type' => 'fanout',
            ],
            [
                'exchange' => 'institution',
                'type' => 'fanout',
            ],
            [
                'exchange' => 'institution-user',
                'type' => 'fanout',
            ],
        ];

        if (env('AUDIT_LOG_EVENTS_EXCHANGE')) {
            $exchanges[] = [
                'exchange' => env('AUDIT_LOG_EVENTS_EXCHANGE'),
                'type' => 'topic',
            ];
        }

        if (env('EMAIL_NOTIFICATION_EXCHANGE')) {
            $exchanges[] = [
                'exchange' => env('EMAIL_NOTIFICATION_EXCHANGE'),
                'type' => 'topic',
            ];
        }

        Config::set('amqp.publisher', [
            'exchanges' => $exchanges,
        ]);

        Artisan::call('amqp:setup');

        Config::set('keycloak.realm_public_key_retrieval_mode', 'config');
        Config::set('keycloak.realm_public_key', env('KEYCLOAK_REALM_PUBLIC_KEY'));

        // FakeFileScanService::bind();

        AuthHelpers::fakeServiceValidationResponse();

        $camundaBaseUrl = env('CAMUNDA_API_URL', 'http://process-definition');
        Http::fake([
            rtrim($camundaBaseUrl, '/').'/*' => Http::response([
                'id' => fake()->uuid(),
                'definitionId' => fake()->uuid(),
                'businessKey' => fake()->uuid(),
            ], 200),
            // Also match any process-definition URL pattern
            'process-definition/*' => Http::response([
                'id' => fake()->uuid(),
                'definitionId' => fake()->uuid(),
                'businessKey' => fake()->uuid(),
            ], 200),
        ]);
    }

    protected function prepareAuthorizedRequest($accessToken): TestCase
    {
        return $this->withHeader('Authorization', 'Bearer '.$accessToken);
    }

    public function assertArrayHasSubsetIgnoringOrder(?array $expectedSubset, ?array $actual): void
    {
        Assertions::assertArrayHasSubsetIgnoringOrder($expectedSubset, $actual);
    }

    public function assertArraysEqualIgnoringOrder(?array $expected, ?array $actual): void
    {
        Assertions::assertArraysEqualIgnoringOrder($expected, $actual);
    }

    /**
     * Assert that a model is soft-deleted (can't be found without withTrashed(), but exists with withTrashed()).
     * This is the correct way to check soft-deleted models, as assertModelMissing() uses raw SQL
     * that doesn't respect soft-deletes.
     */
    protected function assertModelSoftDeleted($model): void
    {
        $modelClass = get_class($model);
        $this->assertNull($modelClass::find($model->id), 'Model should be soft-deleted');
        $this->assertNotNull($modelClass::withTrashed()->find($model->id), 'Model should exist with withTrashed()');
    }
}
