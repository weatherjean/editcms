<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

class TextField extends BaseField
{
    public function toDatabase(mixed $value): string
    {
        return (string) $value;
    }

    public function fromDatabase(string $value): mixed
    {
        return $value;
    }
}
