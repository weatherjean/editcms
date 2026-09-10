<?php

declare(strict_types=1);

namespace Edit\Core\ContentTypes;

use Edit\Core\Fields\NumberField;

final class ContentQuery
{
    public const CORE_SORTS = ['id', 'slug', 'created_at', 'updated_at'];
    private const SCALAR_TYPES = ['text', 'textarea', 'wysiwyg', 'html', 'slug', 'select', 'date', 'datetime', 'number', 'boolean', 'relationship'];

    public static function field(array $schema, string $key, bool $publicOnly): array
    {
        [$path,$field] = ContentSchema::resolve($schema, $key, $publicOnly);
        if (!in_array($field['type'], self::SCALAR_TYPES, true) || ($field['config']['multiple'] ?? false)) {
            throw new \InvalidArgumentException('Only scalar fields support filtering and sorting');
        }
        return [$path, $field];
    }

    public static function parse(array $query, array $schema, bool $publicOnly = true): array
    {
        $out = ['limit' => 10, 'offset' => 0, 'order_by' => 'created_at', 'order_dir' => 'DESC', 'populate' => '', 'field_filters' => []];
        foreach (['limit' => [1, 100], 'offset' => [0, 1000000]] as $key => [$min,$max]) {
            if (!isset($query[$key])) {
                continue;
            }
            $value = $query[$key];
            if ((!is_int($value) && !is_string($value)) || !preg_match('/^\d+$/', (string)$value) || $value < $min || $value > $max) {
                throw new \InvalidArgumentException("{$key} must be an integer between {$min} and {$max}");
            }
            $out[$key] = (int)$value;
        }
        foreach (['order_by', 'order_dir', 'populate', 'fields_only', 'field_groups', 'exclude_open_fields'] as $key) {
            if (isset($query[$key]) && !is_string($query[$key])) {
                throw new \InvalidArgumentException("{$key} must be a string");
            }
        }
        $out['order_by'] = $query['order_by'] ?? $out['order_by'];
        if (!in_array($out['order_by'], self::CORE_SORTS, true)) {
            [$out['order_by']] = self::field($schema, $out['order_by'], $publicOnly);
        }
        $out['order_dir'] = strtoupper($query['order_dir'] ?? $out['order_dir']);
        if (!in_array($out['order_dir'], ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException('order_dir must be ASC or DESC');
        }
        $out['populate'] = $query['populate'] ?? '';
        $filters = $query['fields'] ?? [];
        if (!is_array($filters) || count($filters) > 20) {
            throw new \InvalidArgumentException('fields must contain at most 20 filters');
        }
        foreach ($filters as $key => $value) {
            if (!is_string($key) || !is_scalar($value) || is_bool($value)) {
                throw new \InvalidArgumentException('Field filters must contain scalar values');
            }
            $operator = '=';
            if (preg_match('/^(.+)_(gte|lte|like|not)$/', $key, $match)) {
                $key = $match[1];
                $operator = ['gte' => '>=', 'lte' => '<=', 'like' => 'LIKE', 'not' => '!='][$match[2]];
            }
            [$path,$field] = self::field($schema, $key, $publicOnly);
            $value = self::value($value, $field, $operator);
            $out['field_filters'][] = ['key' => $path, 'operator' => $operator, 'value' => $value];
        }
        return $out;
    }

    public static function value(mixed $value, array $field, string $operator): mixed
    {
        if (!in_array($operator, ['=', '!=', '>=', '<=', 'LIKE'], true) || !is_scalar($value)) {
            throw new \InvalidArgumentException('Invalid field filter');
        }
        if (in_array($field['type'], ['number', 'boolean', 'relationship'], true)) {
            if ($operator === 'LIKE' || NumberField::parse($value) === null) {
                throw new \InvalidArgumentException('Numeric filter requires a finite number and a comparison operator');
            }
            $value = NumberField::parse($value);
            if ($field['type'] === 'boolean' && !in_array($value, [0, 1], true)) {
                throw new \InvalidArgumentException('Boolean filter must be 0 or 1');
            }
        }
        return $value;
    }

    public static function expression(array $field, string $column): string
    {
        return in_array($field['type'], ['number', 'boolean', 'relationship'], true) ? "edit_number({$column})" : $column;
    }
}
