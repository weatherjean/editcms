<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

class MediaField extends BaseField
{
    public function validate(mixed $value, array $config): bool
    {
        if (!parent::validate($value, $config)) {
            return false;
        }

        // Allow multiple media items if config specifies
        $allowMultiple = $config['config']['multiple'] ?? false;

        if (empty($value)) {
            return true;
        }

        // If multiple is allowed, value should be an array
        if ($allowMultiple) {
            if (!is_array($value)) {
                return false;
            }

            // All items in array must be numeric IDs or objects with an 'id' property
            foreach ($value as $item) {
                if (is_numeric($item)) {
                    continue;
                }
                // Allow objects with an 'id' property (will be sanitized)
                if (is_array($item) && isset($item['id']) && is_numeric($item['id'])) {
                    continue;
                }
                return false;
            }

            return true;
        }

        // Single value must be numeric or an object with an 'id' property
        if (is_numeric($value)) {
            return true;
        }

        if (is_array($value) && isset($value['id']) && is_numeric($value['id'])) {
            return true;
        }

        return false;
    }

    public function sanitize(mixed $value, array $config): mixed
    {
        $allowMultiple = $config['config']['multiple'] ?? false;

        if (empty($value)) {
            return null;
        }

        if ($allowMultiple) {
            if (!is_array($value)) {
                return null;
            }

            // Convert all items to integers, handling both IDs and objects
            $sanitized = array_map(function($item) {
                // If it's an object/array with an 'id' property, extract it
                if (is_array($item) && isset($item['id'])) {
                    return (int) $item['id'];
                }
                // Otherwise convert directly to int
                return (int) $item;
            }, $value);

            // Remove any zero values (failed conversions)
            $sanitized = array_filter($sanitized, fn($v) => $v > 0);

            return !empty($sanitized) ? array_values($sanitized) : null;
        }

        // Handle single value - extract ID from object if needed
        if (is_array($value) && isset($value['id'])) {
            return (int) $value['id'];
        }

        return (int) $value;
    }

    public function toDatabase(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }

        // If it's an array, store as JSON
        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }

    public function fromDatabase(string $value): mixed
    {
        if (empty($value)) {
            return null;
        }

        // Try to decode as JSON first
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // Convert all items to integers
            return array_map('intval', $decoded);
        }

        // Otherwise, treat as single integer
        return (int) $value;
    }
}
