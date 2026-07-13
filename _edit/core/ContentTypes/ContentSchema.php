<?php

declare(strict_types=1);

namespace Edit\Core\ContentTypes;

/** Qualified schema identities shared by storage, validation and queries. */
final class ContentSchema
{
    public static function fields(array $schema, bool $publicOnly = false): array
    {
        $out = [];
        if (!empty($schema['field_groups'])) {
            foreach ($schema['field_groups'] as $group) {
                foreach ($group['fields'] ?? [] as $field) {
                    $field['public'] = ($group['public'] ?? true) === true && ($field['public'] ?? true) === true;
                    $out[$group['key'] . '.' . $field['key']] = $field;
                }
            }
        } else {
            foreach ($schema['fields'] ?? [] as $key => $field) {
                $out[$field['key'] ?? $key] = $field;
            }
        }
        if ($schema['allow_open'] ?? false) {
            $out['flexible_content'] = $schema['fields']['flexible_content'] ?? ['key' => 'flexible_content', 'type' => 'flexible_content'];
        }
        return $publicOnly ? array_filter($out, fn ($field) => ($field['public'] ?? true) === true && ($field['target'] ?? $field['config']['target'] ?? '') !== 'user') : $out;
    }

    public static function resolve(array $schema, string $key, bool $publicOnly = false): array
    {
        $all = self::fields($schema);
        $matches = isset($all[$key]) ? [$key => $all[$key]] : array_filter($all, fn ($field) => ($field['key'] ?? '') === $key);
        // Ambiguity is checked before privacy filtering to keep aliases predictable.
        if (count($matches) !== 1) {
            throw new \InvalidArgumentException("Unknown or ambiguous field '{$key}'; use group.field for grouped fields");
        }
        $path = array_key_first($matches);
        if ($publicOnly && !isset(self::fields($schema, true)[$path])) {
            throw new \InvalidArgumentException('Field is not available for public queries');
        }
        return [$path, $matches[$path]];
    }
}
