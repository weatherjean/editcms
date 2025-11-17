<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

class NumberField extends BaseField
{
    public function validate(mixed $value, array $config): bool
    {
        if (!parent::validate($value, $config)) {
            return false;
        }

        if (!empty($value) && !is_numeric($value)) {
            return false;
        }

        return true;
    }

    public function sanitize(mixed $value, array $config): mixed
    {
        if (empty($value)) {
            return null;
        }

        return filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    public function toDatabase(mixed $value): string
    {
        return (string) $value;
    }

    public function fromDatabase(string $value): mixed
    {
        if (empty($value)) {
            return null;
        }

        return strpos($value, '.') !== false ? (float) $value : (int) $value;
    }
}
