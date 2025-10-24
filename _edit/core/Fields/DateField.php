<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

class DateField extends BaseField
{
    public function validate(mixed $value, array $config): bool
    {
        if (!parent::validate($value, $config)) {
            return false;
        }

        if (!empty($value)) {
            $date = \DateTime::createFromFormat('Y-m-d', $value);
            return $date && $date->format('Y-m-d') === $value;
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
