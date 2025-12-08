<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    //use RefreshDatabaseWithCachedEntitySchema;

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

        FakeFileScanService::bind();

        AuthHelpers::fakeServiceValidationResponse();

        $camundaBaseUrl = env('CAMUNDA_API_URL', 'http://process-definition');
        Http::fake([
            rtrim($camundaBaseUrl, '/') . '/*' => Http::response([
                
            ]),
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
}
