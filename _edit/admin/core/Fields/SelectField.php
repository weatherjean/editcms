<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

class SelectField extends BaseField
{
    public function validate(mixed $value, array $config): bool
    {
        if (!parent::validate($value, $config)) {
            return false;
        }

        // Check if value is in allowed options
        if (!empty($value) && isset($config['options'])) {
            return in_array($value, $config['options'], true);
        }

        return true;
    }

    public function toDatabase(mixed $value): string
    {
        return (string) $value;
    }

    public function fromDatabase(string $value): mixed
    {
        return $value;
    }
}
