<?php

namespace App\Http\OpenApiHelpers;

use Attribute;
use OpenApi\Attributes as OA;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class UuidPath extends OA\PathParameter
{
    public function __construct(string $name, ?string $description = 'UUID')
    {
        parent::__construct(
            name: $name,
            description: $description,
            schema: new OA\Schema(
                type: 'string',
                format: 'uuid'
            )
        );
    }
}
