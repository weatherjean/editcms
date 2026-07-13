<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

class WysiwygField extends BaseField
{
    public function toDatabase(mixed $value): string
    {
        return (string) $value;
    }

    public function fromDatabase(string $value): mixed
    {
        return $value;
    }

    public function sanitize(mixed $value, array $config): mixed
    {
        return \Edit\Core\Security\HtmlSanitizer::clean((string)($value ?? ''));
    }
}
