<?php

namespace App\Http\OpenApiHelpers;

use Attribute;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Forbidden extends OA\Response
{
    public function __construct()
    {
        parent::__construct(
            response: Response::HTTP_FORBIDDEN,
            description: 'The provided bearer JWT was insufficient to authorize the operation. '.
            'This may happen for a variety of reasons. '.
            'Examples: the user lacks required privileges, JWT has expired, JWT signature invalid.',
        );
    }
}
