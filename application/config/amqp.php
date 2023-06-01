<?php

use App\Events\ClassifierValues\ClassifierValueDeleted;
use App\Events\ClassifierValues\ClassifierValueSaved;
use App\Events\Institutions\InstitutionDeleted;
use App\Events\Institutions\InstitutionSaved;
use App\Events\InstitutionUsers\InstitutionUserDeleted;
use App\Events\InstitutionUsers\InstitutionUserSaved;
use SyncTools\Events\MessageEventFactory;

return [
    /*
    |--------------------------------------------------------------------------
    | AMQP connection properties
    |--------------------------------------------------------------------------
    */
    'connection' => [
        'host' => env('AMQP_HOST', 'localhost'),
        'port' => env('AMQP_PORT', 5672),
        'username' => env('AMQP_USER', 'guest'),
        'password' => env('AMQP_PASSWORD', 'guest'),
        'vhost' => env('AMQP_VHOST', '/'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AMQP consumer properties (remove if not needed)
    |--------------------------------------------------------------------------
    */
    'consumer' => [
        'queues' => [
            [
                'queue' => 'tv-translation-order.classifier-value',
                'bindings' => [
                    ['exchange' => 'classifier-value'],
                ],
            ],
            [
                'queue' => 'tv-translation-order.institution',
                'bindings' => [
                    ['exchange' => 'institution'],
                ],
            ],
            [
                'queue' => 'tv-translation-order.institution-user',
                'bindings' => [
                    ['exchange' => 'institution-user'],
                ],
            ],
        ],
        'events' => [
            'mode' => MessageEventFactory::MODE_ROUTING_KEY,
            'map' => [
                'classifier-value.saved' => ClassifierValueSaved::class,
                'classifier-value.deleted' => ClassifierValueDeleted::class,
                'institution.saved' => InstitutionSaved::class,
                'institution.deleted' => InstitutionDeleted::class,
                'institution-user.saved' => InstitutionUserSaved::class,
                'institution-user.deleted' => InstitutionUserDeleted::class,
            ],
        ],
    ],
];
