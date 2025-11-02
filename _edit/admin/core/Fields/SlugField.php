<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

class SlugField extends BaseField
{
    public function sanitize(mixed $value, array $config): mixed
    {
        if (empty($value)) {
            return '';
        }

        // Convert to lowercase and replace spaces/special chars with hyphens
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug;
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
