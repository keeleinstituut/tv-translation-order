<?php

if (! function_exists('nullsafe_tap')) {
    function nullsafe_tap($value, $callback)
    {
        if ($value) {
            return tap($value, $callback);
        }

        return $value;
    }
}
