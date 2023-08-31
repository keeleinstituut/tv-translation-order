<?php

namespace App\Http\OpenApiHelpers;

use Attribute;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ResourceResponse extends OA\Response
{
    /** @param  class-string  $dataRef */
    public function __construct(string $dataRef, string $description, int $response = Response::HTTP_OK)
    {
        parent::__construct(
            response: $response,
            description: $description,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'data',
                        ref: $dataRef
                    ),
                ]
            )
        );
    }
}
