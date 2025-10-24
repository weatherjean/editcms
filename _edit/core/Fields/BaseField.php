<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

abstract class BaseField implements FieldType
{
    /**
     * Check if field is required
     */
    protected function isRequired(array $config): bool
    {
        return $config['required'] ?? false;
    }

    /**
     * Get field label
     */
    protected function getLabel(array $config): string
    {
        return $config['label'] ?? 'Field';
    }

    /**
     * Get field name/key
     */
    protected function getName(array $config): string
    {
        return $config['name'] ?? 'field';
    }

    /**
     * Escape HTML attributes
     */
    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Default validation - checks if required field is empty
     */
    public function validate(mixed $value, array $config): bool
    {
        if ($this->isRequired($config) && empty($value)) {
            return false;
        }
        return true;
    }

    /**
     * Default sanitization - trim strings
     */
    public function sanitize(mixed $value, array $config): mixed
    {
        if (is_string($value)) {
            return trim($value);
        }
        return $value;
    }
}
