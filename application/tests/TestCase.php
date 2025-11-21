<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use SyncTools\Traits\RefreshDatabaseWithCachedEntitySchema;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabaseWithCachedEntitySchema;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('amqp.publisher', [
            'exchanges' => [
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
                [
                    'exchange' => env('AUDIT_LOG_EVENTS_EXCHANGE'),
                    'type' => 'topic',
                ],
                [
                    'exchange' => env('EMAIL_NOTIFICATION_EXCHANGE'),
                    'type' => 'topic',
                ],
            ],
        ]);

        Artisan::call('amqp:setup');

        // Mock FileScanService to always return safe results without calling external API
        FakeFileScanService::bind();

        AuthHelpers::fakeServiceValidationResponse();
    }

    protected function prepareAuthorizedRequest($accessToken): TestCase
    {
        return $this->withHeader('Authorization', 'Bearer ' . $accessToken);
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
