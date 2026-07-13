<?php

declare(strict_types=1);

namespace Edit\Core\ContentTypes;

use Edit\Core\Database\Database;

/** Public output is schema-defined; admin hydration is never used here. */
final class PublicSerializer
{
    public function __construct(
        private Database $db,
        private ContentTypeRegistry $registry,
        private ?BlockRegistry $blocks = null,
    ) {}

    public function serialize(array $item, array $populate = [], int $depth = 0): ?array
    {
        $schema = $this->registry->get($item['type']);
        if (!$schema || ($schema['public'] ?? true) !== true || $item['status'] !== 'published') return null;
        $out = array_intersect_key($item, array_flip(['id', 'type', 'slug', 'status', 'created_at', 'updated_at']));
        $fields = $item['fields'] ?? [];
        $out['fields'] = [];
        $grouped = !empty($schema['field_groups']);
        foreach ($schema['field_groups'] ?? [] as $group) {
            $key = $group['key'];
            if (($group['public'] ?? true) === true && is_array($fields[$key] ?? null)) {
                $out['fields'][$key] = $this->fields($fields[$key], $group['fields'], $populate, $depth);
            }
        }
        if (!$grouped) $out['fields'] = $this->fields($fields, $schema['fields'] ?? [], $populate, $depth);
        // Flexible content is a built-in field, with each block governed by its schema.
        if (isset($fields['flexible_content']) && ($schema['fields']['flexible_content']['public'] ?? true) === true) {
            $out['fields']['flexible_content'] = $this->blocks($fields['flexible_content'], $populate, $depth);
        }
        return $out;
    }

    private function fields(array $values, array $definitions, array $populate, int $depth): array
    {
        $out = [];
        if ($depth > 12) return $out;
        foreach ($definitions as $index => $field) {
            $key = $field['key'] ?? $index;
            if (($field['public'] ?? true) !== true || !array_key_exists($key, $values)) continue;
            $value = $values[$key];
            $type = $field['type'] ?? '';
            if ($type === 'relationship') {
                // Login accounts are never public profiles.
                if (($field['target'] ?? $field['config']['target'] ?? 'content') === 'user') continue;
                $expand = in_array($key, $populate, true) && $depth < 3;
                $value = is_array($value) && array_is_list($value)
                    ? array_values(array_filter(array_map(fn($id) => $this->relationship($id, $expand, $populate, $depth), $value)))
                    : $this->relationship($value, $expand, $populate, $depth);
            } elseif ($type === 'media') {
                $value = is_array($value) && array_is_list($value)
                    ? array_values(array_filter(array_map(fn($id) => $this->media($id), $value)))
                    : $this->media($value);
            } elseif ($type === 'repeater') {
                $value = is_array($value) ? array_map(
                    fn($row) => $this->fields(is_array($row) ? $row : [], $field['config']['fields'] ?? $field['fields'] ?? [], $populate, $depth + 1),
                    array_values($value)
                ) : [];
            } elseif ($type === 'flexible_content') {
                $value = $this->blocks($value, $populate, $depth + 1);
            }
            $out[$key] = $value;
        }
        return $out;
    }

    private function blocks(mixed $value, array $populate, int $depth): array
    {
        if (is_string($value)) $value = json_decode($value, true);
        if (!is_array($value) || $depth > 12) return [];
        $out = [];
        foreach ($value as $block) {
            if (!is_array($block) || !is_string($block['block_type'] ?? null)) continue;
            $schema = $this->blocks?->getBlock($block['block_type']);
            if (!$schema || ($schema['public'] ?? true) !== true) continue;
            $clean = array_intersect_key($block, array_flip(['id', 'block_type']));
            $clean['fields'] = $this->fields(is_array($block['fields'] ?? null) ? $block['fields'] : [], $schema['fields'], $populate, $depth + 1);
            $out[] = $clean;
        }
        return $out;
    }

    private function relationship(mixed $id, bool $expand, array $populate, int $depth): ?array
    {
        if (!is_numeric($id) || (int)$id < 1) return null;
        $row = $this->db->table('content')->where('id', (int)$id)->where('status', 'published')->first();
        if (!$row) return null;
        $schema = $this->registry->get($row['type']);
        if (!$schema || ($schema['public'] ?? true) !== true) return null;
        if (!$expand) return array_intersect_key($row, array_flip(['id', 'type', 'slug']));
        $type = new ContentType($this->db, $row['type'], $schema, $this->blocks, false);
        return $this->serialize($type->find((int)$id), $populate, $depth + 1);
    }

    private function media(mixed $id): ?array
    {
        if (!is_numeric($id)) return null;
        $row = $this->db->table('media')->where('id', (int)$id)->first();
        return $row ? addMediaUrl($row) : null;
    }
}
