<?php

declare(strict_types=1);

namespace Edit\Core\ContentTypes;

use Edit\Core\Security\HtmlSanitizer;

/** Sanitize legacy stored HTML without rewriting or validating historical records. */
final class ContentHtml
{
    public static function clean(array $values, array $schema, ?BlockRegistry $blocks): array
    {
        foreach ($schema['field_groups'] ?? [] as $group) {
            if (is_array($values[$group['key']] ?? null)) {
                $values[$group['key']] = self::fields($values[$group['key']], $group['fields'] ?? [], $blocks, 0);
            }
        }
        if (empty($schema['field_groups'])) {
            $values = self::fields($values, $schema['fields'] ?? [], $blocks, 0);
        }
        if (isset($values['flexible_content'])) {
            $values['flexible_content'] = self::blocks($values['flexible_content'], $blocks, 0);
        }
        return $values;
    }

    private static function fields(array $values, array $definitions, ?BlockRegistry $blocks, int $depth): array
    {
        if ($depth > 12) {
            return [];
        }
        foreach ($definitions as $key => $field) {
            $key = $field['key'] ?? $key;
            if (!array_key_exists($key, $values)) {
                continue;
            }
            if (in_array($field['type'] ?? '', ['html', 'wysiwyg'], true)) {
                $values[$key] = HtmlSanitizer::clean(is_string($values[$key]) ? $values[$key] : '');
            } elseif (($field['type'] ?? '') === 'repeater' && is_array($values[$key])) {
                foreach ($values[$key] as &$row) {
                    if (is_array($row)) {
                        $row = self::fields($row, $field['config']['fields'] ?? $field['fields'] ?? [], $blocks, $depth + 1);
                    }
                }
                unset($row);
            } elseif (($field['type'] ?? '') === 'flexible_content') {
                $values[$key] = self::blocks($values[$key], $blocks, $depth + 1);
            }
        }
        return $values;
    }

    private static function blocks(mixed $value, ?BlockRegistry $blocks, int $depth): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (!is_array($value) || $depth > 12) {
            return [];
        }
        foreach ($value as &$block) {
            if (!is_array($block) || !is_string($block['block_type'] ?? null)) {
                continue;
            }
            $schema = $blocks?->getBlock($block['block_type']);
            if ($schema && is_array($block['fields'] ?? null)) {
                $block['fields'] = self::fields($block['fields'], $schema['fields'], $blocks, $depth + 1);
            }
        }
        return $value;
    }
}
