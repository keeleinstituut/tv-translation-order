<?php

namespace App\Http\OpenApiHelpers;

use Attribute;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Unauthorized extends OA\Response
{
    public function __construct()
    {
        parent::__construct(
            response: Response::HTTP_UNAUTHORIZED,
            description: 'Authentication was not possible. '.
            'This may happen for a variety of reasons. '.
            'Examples: no authorization header, invalid JWT, missing data from JWT.',
        );
    }
}
