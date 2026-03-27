<?php

namespace App\Exceptions;

use Illuminate\Database\QueryException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CalendarSlotConflictException extends HttpException
{
    public function __construct(string $message = 'The selected time slot is no longer available.')
    {
        parent::__construct(Response::HTTP_CONFLICT, $message);
    }

    /**
     * Execute callback, catching DB constraint violations (exclusion/unique)
     * and converting them to a 409 response.
     */
    public static function catchConstraintViolation(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (QueryException $e) {
            if (in_array($e->getCode(), ['23P01', '23505'])) {
                throw new self();
            }
            throw $e;
        }
    }
}
