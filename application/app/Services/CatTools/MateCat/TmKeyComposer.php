<?php

namespace App\Services\CatTools\MateCat;

use App\Models\CatToolTmKey;

class TmKeyComposer
{
    public static function compose(CatToolTmKey $tmKey): string
    {
        if ($tmKey->is_writable) {
            return "$tmKey->key:rw";
        }

        return "$tmKey->key:r";
    }
}
