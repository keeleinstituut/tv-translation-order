<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CalendarSlotConflictException extends \RuntimeException
{
    public function __construct(string $message = 'The selected time slot is no longer available.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(
            ['message' => $this->getMessage()],
            Response::HTTP_CONFLICT,
        );
    }
}
