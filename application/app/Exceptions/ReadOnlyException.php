<?php

namespace App\Exceptions;

use Exception;

class ReadOnlyException extends Exception
{
    protected $message = "Calling of method is not allowed for readonly model";
}
