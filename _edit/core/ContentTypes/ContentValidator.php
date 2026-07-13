<?php

declare(strict_types=1);
namespace Edit\Core\ContentTypes;

use Edit\Core\Database\Database;

final class ContentValidator
{
    public function __construct(private Database $db, private ?BlockRegistry $blocks = null) {}

    public function validate(mixed $values, array $schema, bool $publishing): array
    {
        if (!is_array($values)) $this->fail('fields', 'must be an object');
        $definitions = [];
        if (!empty($schema['field_groups'])) {
            foreach ($schema['field_groups'] as $group) $definitions[$group['key']] = ['key'=>$group['key'],'type'=>'group','fields'=>$group['fields'] ?? []];
        } else $definitions = $schema['fields'] ?? [];
        if ($schema['allow_open'] ?? false) $definitions['flexible_content'] = ['key'=>'flexible_content','type'=>'flexible_content'];
        return $this->fields($values, $definitions, $publishing, 'fields', 0);
    }

    private function fail(string $path, string $reason): never
    {
        throw new \InvalidArgumentException("{$path}: {$reason}");
    }

    private function fields(array $values, array $definitions, bool $publishing, string $path, int $depth): array
    {
        if ($depth > 12) $this->fail($path, 'nesting exceeds 12 levels');
        $map = [];
        foreach ($definitions as $key => $field) $map[$field['key'] ?? $key] = $field;
        foreach ($values as $key => $_) if (!isset($map[$key])) $this->fail("{$path}.{$key}", 'unknown field');
        $out = [];
        foreach ($map as $key => $field) {
            $present = array_key_exists($key, $values);
            $value = $values[$key] ?? null;
            $name = "{$path}.{$key}";
            if (($field['type'] ?? '') === 'group') {
                if ($present && !is_array($value)) $this->fail($name, 'must be an object');
                $clean = $this->fields($value ?? [], $field['fields'], $publishing, $name, $depth + 1);
                if ($present || $clean) $out[$key] = $clean;
                continue;
            }
            $empty = $value === null || $value === '' || $value === [] || (is_string($value) && trim($value) === '');
            if ($publishing && ($field['required'] ?? false) && $empty) $this->fail($name, 'is required before publishing');
            if (!$present) continue;
            $config = $field['config'] ?? [];
            $type = $field['type'] ?? '';
            if ($type === 'repeater' || $type === 'flexible_content') {
                if ($value === null || $value === '') $value = [];
                if (!is_array($value) || !array_is_list($value)) $this->fail($name, 'must be a list');
                foreach (['min'=>'>=', 'max'=>'<='] as $bound => $_) {
                    $limit = $config[$bound] ?? null;
                    if ($limit !== null && $limit !== '' && (($bound === 'min' && $publishing && count($value) < $limit) || ($bound === 'max' && count($value) > $limit))) $this->fail($name, "item count violates {$bound}");
                }
                $out[$key] = [];
                foreach ($value as $i => $row) {
                    if (!is_array($row)) $this->fail("{$name}.{$i}", 'must be an object');
                    if ($type === 'repeater') {
                        $out[$key][] = $this->fields($row, $config['fields'] ?? $field['fields'] ?? [], $publishing, "{$name}.{$i}", $depth + 1);
                    } else {
                        if (!is_string($row['block_type'] ?? null)) $this->fail("{$name}.{$i}", 'block_type is required');
                        $block = $this->blocks?->getBlock($row['block_type']);
                        if (!$block) $this->fail("{$name}.{$i}", 'unknown block type');
                        if (array_diff(array_keys($row), ['id','block_type','fields'])) $this->fail("{$name}.{$i}", 'unknown block property');
                        if (isset($row['id']) && !is_string($row['id']) && !is_int($row['id'])) $this->fail("{$name}.{$i}.id", 'must be a string or integer');
                        if (!is_array($row['fields'] ?? null)) $this->fail("{$name}.{$i}.fields", 'must be an object');
                        $row['fields'] = $this->fields($row['fields'], $block['fields'], $publishing, "{$name}.{$i}.fields", $depth + 1);
                        $out[$key][] = $row;
                    }
                }
                continue;
            }
            if ($type === 'media' || $type === 'relationship') {
                if ($empty) { $out[$key] = null; continue; }
                $multiple = $config['multiple'] ?? false;
                $ids = $multiple ? $value : [$value];
                if (!is_array($ids) || !array_is_list($ids)) $this->fail($name, 'must be a list of IDs');
                $clean = [];
                foreach ($ids as $id) {
                    if (is_array($id)) $id = $id['id'] ?? null;
                    if (filter_var($id, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) === false || is_bool($id)) $this->fail($name, 'must reference a positive integer ID');
                    $target = $field['target'] ?? $config['target'] ?? 'content';
                    $table = $type === 'media' ? 'media' : ($target === 'user' ? 'users' : 'content');
                    $record = $this->db->table($table)->where('id', (int)$id)->first();
                    if (!$record) $this->fail($name, 'referenced record no longer exists');
                    if ($table === 'content' && !empty($config['post_type']) && $record['type'] !== $config['post_type']) $this->fail($name, 'wrong relationship content type');
                    $clean[] = (int)$id;
                }
                $out[$key] = $multiple ? $clean : $clean[0];
                continue;
            }
            $class = 'Edit\\Core\\Fields\\' . ucfirst($type) . 'Field';
            if (!class_exists($class)) $this->fail($name, 'unsupported field type');
            if (!in_array($type, ['number','boolean'], true) && $value !== null && !is_string($value)) $this->fail($name, 'must be a string');
            $instance = new $class();
            $field['required'] = $publishing && ($field['required'] ?? false);
            if (!$instance->validate($value, $field)) $this->fail($name, 'invalid value or outside configured bounds');
            $clean = $instance->sanitize($value, $field);
            if ($field['required'] && ($clean === null || $clean === '' || $clean === [])) $this->fail($name, 'is required before publishing');
            $out[$key] = $clean;
        }
        return $out;
    }
}
