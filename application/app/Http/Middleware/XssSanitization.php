<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TransformsRequest;

class XssSanitization extends TransformsRequest
{
    /**
     * The attributes that should not be trimmed.
     *
     * @var array<int, string>
     */
    protected array $except = [
        //
    ];

    public function transform($key, $value)
    {
        if (in_array($key, $this->except, true) || ! is_string($value)) {
            return $value;
        }

        return htmlspecialchars($value);
    }
}
