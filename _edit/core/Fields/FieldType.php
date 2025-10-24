<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

interface FieldType
{
    /**
     * Validate the field value
     */
    public function validate(mixed $value, array $config): bool;

    /**
     * Sanitize the field value
     */
    public function sanitize(mixed $value, array $config): mixed;

    /**
     * Convert value to database storage format (string)
     */
    public function toDatabase(mixed $value): string;

    /**
     * Convert value from database storage format
     */
    public function fromDatabase(string $value): mixed;
}
