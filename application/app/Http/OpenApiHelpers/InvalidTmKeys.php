<?php

namespace App\Http\OpenApiHelpers;

use Attribute;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class InvalidTmKeys extends OA\Response
{
    public function __construct()
    {
        parent::__construct(
            response: Response::HTTP_BAD_REQUEST,
            description: 'Sub-project TM keys are invalid',
            content: new OA\JsonContent(
                required: ['errors'],
                properties: [
                    new OA\Property(property: 'errors', type: 'object'),
                ],
                type: 'object',
                example: [
                    'errors' => [
                        'tm_keys' => [
                            [
                                'message' => 'TM key {id} not found',
                                'tm_key' => '14d6a8ee-fbbd-4e6a-8ef0-24a381190a35',
                            ],
                        ],
                    ],
                ]
            ),
        );
    }
}
