<?php

namespace App\Enums\Concerns;

trait ParsesEnum
{
    public static function parse(string | self $value): self
    {
        if ($value === null) {
            // Handle null case, either return a default value or throw an exception
            throw new \InvalidArgumentException('Enum value cannot be null');
        }

        if ($value instanceof self) {
            return $value;
        }

        return self::from($value);
    }
}
