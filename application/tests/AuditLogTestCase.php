<?php

namespace Tests;

use App\Models\CachedEntities\InstitutionUser;
use AuditLogClient\Enums\AuditLogEventFailureType;
use AuditLogClient\Enums\AuditLogEventType;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use PhpAmqpLib\Channel\AMQPChannel;
use SyncTools\AmqpConnectionRegistry;

/**
 * Important notes:
 *  * These tests depend on RabbitMQ running and working.
 *  * These tests assume the audit-log-events queue is empty.
 *  ** Queue must be emptied if there are old messages in the way.
 */
class AuditLogTestCase extends TestCase
{
    use CreatesApplication;

    const TRACE_ID = '123-ABC';

    public function setUp(): void
    {
        parent::setUp();

        Config::set('amqp.consumer', [
            'queues' => [
                [
                    'queue' => env('AUDIT_LOG_EVENTS_QUEUE'),
                    'bindings' => [
                        ['exchange' => env('AUDIT_LOG_EVENTS_EXCHANGE')],
                    ],
                ],
            ],
        ]);

        Artisan::call('amqp:setup');

        AuthHelpers::fakeServiceValidationResponse();
        /** @var AMQPChannel $channel */
        $channel = app(AmqpConnectionRegistry::class)->getConnection()->channel();
        $channel->queue_purge(env('AUDIT_LOG_EVENTS_QUEUE'));
    }

    protected function assertMessageIsReceived(array $expectedBody, bool $checkIsSubset = false): void
    {
        $actualBody = $this->retrieveLatestAuditLogMessageBody();

        if ($checkIsSubset) {
            Assertions::assertArrayHasSubsetIgnoringOrder($expectedBody, $actualBody);
        } else {
            Assertions::assertArraysEqualIgnoringOrder($expectedBody, $actualBody);
        }
    }

    protected function retrieveLatestAuditLogMessageBody(): ?array
    {
        /** @var AMQPChannel $channel */
        $channel = app(AmqpConnectionRegistry::class)->getConnection()->channel();
        $message = $channel->basic_get(env('AUDIT_LOG_EVENTS_QUEUE'));

        if (empty($message)) {
            return null;
        }

        $message->ack(true);

        return json_decode($message->getBody(), true);
    }

    public static function createExpectedMessageBodyGivenActingUser(
        AuditLogEventType $eventType,
        CarbonInterface $happenedAt,
        InstitutionUser $institutionUser,
        array $eventParameters = null,
        AuditLogEventFailureType $failureType = null,
        string $traceId = null,
    ): array {
        return static::createExpectedMessageBody(
            $eventType,
            $happenedAt,
            $institutionUser->user->personal_identification_code,
            $institutionUser->user->forename,
            $institutionUser->user->surname,
            $institutionUser->id,
            $institutionUser->institution_id,
            $institutionUser->department->id,
            $eventParameters,
            $failureType?->value,
            $traceId,
        );
    }

    public static function createExpectedMessageBody(
        AuditLogEventType $eventType,
        CarbonInterface $happenedAt,
        string $actingUserPic = null,
        string $actingUserForename = null,
        string $actingUserSurname = null,
        string $actingInstitutionUserId = null,
        string $contextInstitutionId = null,
        string $contextDepartmentId = null,
        array $eventParameters = null,
        AuditLogEventFailureType $failureType = null,
        string $traceId = null,
    ): array {
        return [
            'happened_at' => $happenedAt->toISOString(),
            'acting_user_pic' => $actingUserPic,
            'acting_user_forename' => $actingUserForename,
            'acting_user_surname' => $actingUserSurname,
            'event_type' => $eventType->value,
            'failure_type' => $failureType?->value,
            'context_institution_id' => $contextInstitutionId,
            'acting_institution_user_id' => $actingInstitutionUserId,
            'context_department_id' => $contextDepartmentId,
            'event_parameters' => $eventParameters,
            'trace_id' => $traceId ?? self::TRACE_ID,
        ];
    }

    protected function prepareAuthorizedRequest($accessToken): static
    {
        return $this->withHeader('Authorization', 'Bearer '.$accessToken);
    }
}
