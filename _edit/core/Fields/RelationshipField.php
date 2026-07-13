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
        return !empty($value) ? (int)$value : null;
    }

    public function toDatabase(mixed $value): string
    {
        return is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : (string) ($value ?? '');
    }

    public function fromDatabase(string $value): mixed
    {
        $decoded = json_decode($value, true);
        return is_array($decoded) ? array_map('intval', $decoded) : (!empty($value) ? (int)$value : null);
    }
}
