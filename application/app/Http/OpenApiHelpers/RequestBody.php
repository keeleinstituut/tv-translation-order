<?php

namespace App\Http\OpenApiHelpers;

use Attribute;
use OpenApi\Attributes as OA;

/**
 * Ideally, referencing the request body would be done just by FQCN.
 * However, that does not seem to be supported by swagger-php.
 * Therefore, we have to manually specify the $ref URI (#/components/requestBodies/...).
 *
 * @see https://github.com/zircote/swagger-php/issues/1456
 */
#[Attribute(Attribute::TARGET_METHOD)]
class RequestBody extends OA\RequestBody
{
    public function __construct(string $requestBodyKey)
    {
        parent::__construct(ref: '#/components/requestBodies/'.$requestBodyKey);
    }
}
