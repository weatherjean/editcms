<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

class NumberField extends BaseField
{
    public static function parse(mixed $value): int|float|null
    {
        if (is_bool($value) || !is_numeric($value) || !is_finite((float)$value)) {
            return null;
        }
        return $value + 0;
    }

    public function validate(mixed $value, array $config): bool
    {
        if (!parent::validate($value, $config)) {
            return false;
        }
        if ($value === null || $value === '') {
            return true;
        }
        $number = self::parse($value);
        if ($number === null) {
            return false;
        }
        $options = $config['config'] ?? $config;
        foreach (['min', 'max'] as $bound) {
            $limit = $options[$bound] ?? null;
            if ($limit !== null && $limit !== '' && ($bound === 'min' ? $number < $limit : $number > $limit)) {
                return false;
            }
        }
        $step = $options['step'] ?? null;
        if (is_numeric($step) && $step > 0) {
            $base = is_numeric($options['min'] ?? null) ? $options['min'] : 0;
            $steps = ($number - $base) / $step;
            if (abs($steps - round($steps)) > 1e-8) {
                return false;
            }
        }
        return true;
    }

    public function sanitize(mixed $value, array $config): mixed
    {
        return self::parse($value);
    }

    public function toDatabase(mixed $value): string
    {
        return (string)$value;
    }

    public function fromDatabase(string $value): mixed
    {
        return self::parse($value);
    }
}
