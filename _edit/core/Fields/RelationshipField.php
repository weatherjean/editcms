<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

class RelationshipField extends BaseField
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
        return !empty($value) ? (int) $value : null;
    }

    public function toDatabase(mixed $value): string
    {
        return (string) ($value ?? '');
    }

    public function fromDatabase(string $value): mixed
    {
        return !empty($value) ? (int) $value : null;
    }
}
