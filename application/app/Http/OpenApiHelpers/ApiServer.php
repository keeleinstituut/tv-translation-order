<?php

namespace App\Http\OpenApiHelpers;

use Attribute;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[Attribute(Attribute::TARGET_CLASS)]
class ApiServer extends OA\Server
{
    public function __construct(string $description = 'API root')
    {
        parent::__construct(
            url: Str::of(Config::get('app.url'))
                ->finish('/')
                ->append('api/'),
            description: $description
        );
    }
}
