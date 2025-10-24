<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

class RepeaterField extends BaseField
{
    public function validate(mixed $value, array $config): bool
    {
        if ($this->isRequired($config) && empty($value)) {
            return false;
        }

        // Value should be an array
        if (!empty($value) && !is_array($value)) {
            return false;
        }

        return true;
    }

    public function sanitize(mixed $value, array $config): mixed
    {
        if (!is_array($value)) {
            return [];
        }

        return $value;
    }

    public function toDatabase(mixed $value): string
    {
        return json_encode($value ?? []);
    }

    public function fromDatabase(string $value): mixed
    {
        if (empty($value)) {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
