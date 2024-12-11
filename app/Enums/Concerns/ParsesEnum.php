<?php

namespace App\Enums\Concerns;

trait ParsesEnum
{
    public static function parse(string | self $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::from($value);
    }
}
