<?php

namespace App\Http\OpenApiHelpers;

use Attribute;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class PaginatedCollectionResponse extends OA\Response
{
    /** @param  class-string  $itemsRef */
    public function __construct(string $itemsRef, string $description = 'Data', int $response = Response::HTTP_OK)
    {
        parent::__construct(
            response: $response,
            description: $description,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(ref: $itemsRef)
                    ),
                    new OA\Property(
                        property: 'links',
                        required: ['first', 'last', 'prev', 'next'],
                        properties: [
                            new OA\Property(property: 'first', type: 'string', format: 'uri'),
                            new OA\Property(property: 'last', type: 'string', format: 'uri'),
                            new OA\Property(property: 'prev', type: 'string', format: 'uri', nullable: true),
                            new OA\Property(property: 'next', type: 'string', format: 'uri', nullable: true),
                        ],
                        type: 'object'
                    ),
                    new OA\Property(
                        property: 'meta',
                        required: ['total', 'from', 'to', 'current_page', 'last_page', 'per_page'],
                        properties: [
                            new OA\Property(property: 'total', type: 'integer'),
                            new OA\Property(property: 'from', type: 'integer'),
                            new OA\Property(property: 'to', type: 'integer'),
                            new OA\Property(property: 'current_page', type: 'integer'),
                            new OA\Property(property: 'last_page', type: 'integer'),
                            new OA\Property(property: 'per_page', type: 'integer'),
                        ],
                        type: 'object'
                    ),
                ]
            )
        );
    }
}
