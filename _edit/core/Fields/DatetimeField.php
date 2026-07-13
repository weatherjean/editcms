<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

class DatetimeField extends BaseField
{
    public function validate(mixed $value, array $config): bool
    {
        if (!parent::validate($value, $config)) {
            return false;
        }

        if ($value === null || $value === '') return true;
        if (!is_string($value)) return false;
        foreach (['Y-m-d\\TH:i', 'Y-m-d\\TH:i:s', 'Y-m-d\\TH:i:s\\Z', 'Y-m-d\\TH:i:sP', 'Y-m-d H:i:s'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $value, new \DateTimeZone('UTC'));
            if ($date && $date->format($format) === $value) return true;
        }
        return false;
    }

    public function sanitize(mixed $value, array $config): mixed
    {
        return $this->toDatabase($value);
    }

    public function toDatabase(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }

        // Convert to ISO 8601 format for storage
        $date = new \DateTime($value, new \DateTimeZone('UTC'));
        $date->setTimezone(new \DateTimeZone('UTC'));
        return $date->format('Y-m-d\TH:i:s\Z');
    }

    public function fromDatabase(string $value): mixed
    {
        return $value;
    }
}
