<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

class BooleanField extends BaseField
{
    public function sanitize(mixed $value, array $config): mixed
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function toDatabase(mixed $value): string
    {
        return $value ? '1' : '0';
    }

    public function fromDatabase(string $value): mixed
    {
        return $value === '1';
    }
}
