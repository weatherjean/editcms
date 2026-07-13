<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

class BooleanField extends BaseField
{
    public function validate(mixed $value, array $config): bool
    {
        if (!parent::validate($value, $config)) return false;
        return in_array($value, [null, '', true, false, 0, 1, '0', '1'], true);
    }

    public function sanitize(mixed $value, array $config): mixed
    {
        return $value === null || $value === '' ? null : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function toDatabase(mixed $value): string
    {
        return $value === null ? '' : ($value ? '1' : '0');
    }

    public function fromDatabase(string $value): mixed
    {
        return $value === '' ? null : $value === '1';
    }
}
