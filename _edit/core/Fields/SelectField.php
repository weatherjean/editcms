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

        if ($value !== null && $value !== '') {
            $choices = $config['config']['choices'] ?? $config['choices'] ?? null;
            $options = $choices !== null ? array_map('strval', array_keys($choices)) : ($config['options'] ?? []);
            return in_array($value, $options, true);
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
