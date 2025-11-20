<?php

declare(strict_types=1);

use Edit\Core\Database\Database;
use Edit\Core\ContentTypes\ContentTypeRegistry;
use Edit\Core\ContentTypes\BlockRegistry;
use Edit\Core\ContentTypes\ContentType;

/**
 * Public query API routes (no auth required)
 *
 * Routes:
 * - GET /public/{type} - Query published content
 * - GET /public/{type}/{slug} - Get single published content by slug
 * - GET /public/docs - Get API documentation
 */
function handlePublicRoutes(string $method, string $path, Database $db, ContentTypeRegistry $registry, BlockRegistry $blocks): bool
{
    if (str_starts_with($path, '/public/') && $path !== '/public/docs') {
        checkRateLimit($db, 'public_api', 100, 1);
    }

    if ($path === '/public/docs' && $method === 'GET') {
        $docsPath = EDIT_BASE_PATH . '/admin/PUBLIC-API.md';
        if (file_exists($docsPath)) {
            header('Content-Type: text/markdown; charset=utf-8');
            echo file_get_contents($docsPath);
            exit;
        }
        sendError('API documentation not found', 404);
        return true;
    }

    if ($method === 'GET' && preg_match('#^/public/([a-z_-]+)(/([a-z0-9_-]+))?$#', $path, $matches)) {
        $type = $matches[1];
        $slug = $matches[3] ?? null;

        if (!$registry->exists($type)) {
            sendError("Content type '{$type}' not found", 404);
        }

        $contentType = new ContentType($db, $type, $registry->get($type));

        try {
            if ($slug) {
                $item = getPublicContentBySlug($contentType, $registry, $slug);
                if (!$item) {
                    sendError('Content not found', 404);
                }
                sendJson($item);
            } else {
                $result = getPublicContentList($contentType, $registry, $type);
                sendJson($result);
            }
        } catch (\Exception $e) {
            sendError($e->getMessage(), 500);
        }
        return true;
    }

    return false;
}

/**
 * Get single published content item by slug
 */
function getPublicContentBySlug(ContentType $contentType, ContentTypeRegistry $registry, string $slug): ?array
{
    $items = $contentType->all(['status' => 'published', 'slug' => $slug, 'limit' => 1]);

    if (empty($items)) {
        return null;
    }

    $item = $items[0];

    $item = applyFieldSelection($item, $registry, $_GET);

    $item = populateRelationships($item, $contentType, $registry, $_GET['populate'] ?? '');

    unset($item['author_id']);

    return $item;
}

/**
 * Get list of published content with filters, pagination, sorting
 */
function getPublicContentList(ContentType $contentType, ContentTypeRegistry $registry, string $type): array
{
    $params = parsePublicQueryParams($_GET, $registry->get($type));

    $filters = ['status' => 'published'];

    $filters['limit'] = $params['limit'];
    $filters['offset'] = $params['offset'];

    if ($params['order_by']) {
        $filters['order_by'] = $params['order_by'];
        $filters['order_dir'] = $params['order_dir'];
    }

    if (!empty($params['field_filters'])) {
        $filters['field_filters'] = $params['field_filters'];
    }

    $totalFilters = ['status' => 'published'];
    if (!empty($params['field_filters'])) {
        $totalFilters['field_filters'] = $params['field_filters'];
    }
    $total = $contentType->count($totalFilters);

    $items = $contentType->all($filters);

    foreach ($items as &$item) {
        $item = applyFieldSelection($item, $registry, $_GET);

        $item = populateRelationships($item, $contentType, $registry, $params['populate']);

        unset($item['author_id']);
    }

    return [
        'data' => $items,
        'meta' => [
            'total' => $total,
            'limit' => $params['limit'],
            'offset' => $params['offset'],
            'has_more' => ($params['offset'] + $params['limit']) < $total
        ]
    ];
}

/**
 * Parse and validate query parameters
 * Validates field names against content type schema to prevent SQL injection
 */
function parsePublicQueryParams(array $query, array $contentTypeConfig): array
{
    $params = [
        'limit' => 10,
        'offset' => 0,
        'order_by' => 'created_at',
        'order_dir' => 'DESC',
        'populate' => '',
        'field_filters' => []
    ];

    // Build list of valid field keys from schema
    $validFieldKeys = [];
    foreach ($contentTypeConfig['field_groups'] as $group) {
        foreach ($group['fields'] as $field) {
            $validFieldKeys[] = $field['key'];
        }
    }

    if (isset($query['limit'])) {
        $limit = (int) $query['limit'];
        if ($limit < 1 || $limit > 100) {
            sendError('Invalid parameter \'limit\': must be between 1 and 100', 400);
        }
        $params['limit'] = $limit;
    }

    if (isset($query['offset'])) {
        $offset = (int) $query['offset'];
        if ($offset < 0) {
            sendError('Invalid parameter \'offset\': must be >= 0', 400);
        }
        $params['offset'] = $offset;
    }

    if (isset($query['order_by'])) {
        $params['order_by'] = $query['order_by'];
    }

    if (isset($query['order_dir'])) {
        $orderDir = strtoupper($query['order_dir']);
        if (!in_array($orderDir, ['ASC', 'DESC'])) {
            sendError('Invalid parameter \'order_dir\': must be ASC or DESC', 400);
        }
        $params['order_dir'] = $orderDir;
    }

    if (isset($query['populate'])) {
        $params['populate'] = $query['populate'];
    }

    foreach ($query as $key => $value) {
        if (preg_match('/^fields\[([^\]]+)\]$/', $key, $matches)) {
            $fieldKey = $matches[1];

            $operator = '=';
            if (preg_match('/^(.+)_(gte|lte|like|not)$/', $fieldKey, $opMatches)) {
                $fieldKey = $opMatches[1];
                $operator = match($opMatches[2]) {
                    'gte' => '>=',
                    'lte' => '<=',
                    'like' => 'LIKE',
                    'not' => '!=',
                };
            }

            // Validate field key exists in schema (prevent SQL injection)
            if (!in_array($fieldKey, $validFieldKeys)) {
                sendError("Invalid field filter: '{$fieldKey}' is not a valid field for this content type", 400);
            }

            $params['field_filters'][] = [
                'key' => $fieldKey,
                'operator' => $operator,
                'value' => $value
            ];
        }
    }

    return $params;
}

/**
 * Apply field selection based on fields_only, field_groups, exclude_open_fields
 * Works with grouped field structure
 */
function applyFieldSelection(array $item, ContentTypeRegistry $registry, array $query): array
{
    $fields = $item['fields'] ?? [];

    // Parse flexible_content if it's a JSON string
    if (isset($fields['flexible_content']) && is_string($fields['flexible_content'])) {
        $decoded = json_decode($fields['flexible_content'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $fields['flexible_content'] = $decoded;
        }
    }

    // Get field group definitions for this content type
    $contentTypeConfig = $registry->get($item['type']);

    // Apply field_groups filter (filter which field groups to include)
    if (isset($query['field_groups'])) {
        $requestedGroups = array_map('trim', explode(',', $query['field_groups']));

        // Keep only requested field groups (and flexible_content if it exists)
        $filteredFields = [];
        foreach ($fields as $key => $value) {
            if ($key === 'flexible_content' || in_array($key, $requestedGroups)) {
                $filteredFields[$key] = $value;
            }
        }
        $fields = $filteredFields;
    }

    // Apply exclude_open_fields (remove flexible_content)
    if (isset($query['exclude_open_fields']) && $query['exclude_open_fields'] === 'true') {
        unset($fields['flexible_content']);
    }

    // Apply fields_only (filter specific fields within field groups)
    if (isset($query['fields_only'])) {
        $requestedFields = array_map('trim', explode(',', $query['fields_only']));

        foreach ($fields as $fieldGroupKey => &$fieldGroup) {
            if ($fieldGroupKey === 'flexible_content') {
                continue; // Skip flexible_content
            }

            if (is_array($fieldGroup)) {
                // Filter fields within this group
                $fieldGroup = array_filter($fieldGroup, function($fieldKey) use ($requestedFields) {
                    return in_array($fieldKey, $requestedFields);
                }, ARRAY_FILTER_USE_KEY);

                // Remove empty field groups
                if (empty($fieldGroup)) {
                    unset($fields[$fieldGroupKey]);
                }
            }
        }
    }

    $item['fields'] = $fields;
    return $item;
}

/**
 * Populate relationship fields
 * Works with grouped field structure
 */
function populateRelationships(array $item, ContentType $contentType, ContentTypeRegistry $registry, string $populateParam): array
{
    if (empty($populateParam)) {
        return $item;
    }

    $fieldsToPopulate = array_map('trim', explode(',', $populateParam));
    $db = $contentType->getDatabase();

    // Populate fields within field groups
    foreach ($item['fields'] as $fieldGroupKey => &$fieldGroup) {
        if ($fieldGroupKey === 'flexible_content') {
            // Handle flexible_content separately
            if (is_array($fieldGroup)) {
                foreach ($fieldGroup as &$block) {
                    if (isset($block['fields']) && is_array($block['fields'])) {
                        $block['fields'] = populateNestedRelationships($block['fields'], $fieldsToPopulate, $db, $registry);
                    }
                }
            }
            continue;
        }

        if (!is_array($fieldGroup)) {
            continue;
        }

        // Populate fields within this field group
        foreach ($fieldsToPopulate as $fieldKey) {
            if (!isset($fieldGroup[$fieldKey])) {
                continue;
            }

            $value = $fieldGroup[$fieldKey];

            // Handle both single relationships and arrays
            if (is_array($value) && !empty($value)) {
                // Check if it's an array of IDs (multiple relationships)
                if (is_numeric($value[0] ?? null)) {
                    $populated = [];
                    foreach ($value as $relatedId) {
                        if (is_numeric($relatedId)) {
                            $related = fetchRelatedContent($db, $registry, (int) $relatedId);
                            if ($related) {
                                $populated[] = $related;
                            }
                        }
                    }
                    $fieldGroup[$fieldKey] = $populated;
                }
            } elseif (is_numeric($value)) {
                // Single relationship
                $related = fetchRelatedContent($db, $registry, (int) $value);
                if ($related) {
                    $fieldGroup[$fieldKey] = $related;
                }
            }
        }
    }

    return $item;
}

/**
 * Recursively populate relationship fields in nested structures (blocks, repeaters)
 */
function populateNestedRelationships(array $fields, array $fieldsToPopulate, Database $db, ContentTypeRegistry $registry): array
{
    foreach ($fields as $key => $value) {
        // If this field name matches the populate list and it's a relationship ID
        if (in_array($key, $fieldsToPopulate)) {
            if (is_numeric($value)) {
                // Single relationship
                $related = fetchRelatedContent($db, $registry, (int) $value);
                if ($related) {
                    $fields[$key] = $related;
                }
            } elseif (is_array($value)) {
                // Array of relationship IDs
                $populated = [];
                foreach ($value as $relatedId) {
                    if (is_numeric($relatedId)) {
                        $related = fetchRelatedContent($db, $registry, (int) $relatedId);
                        if ($related) {
                            $populated[] = $related;
                        }
                    }
                }
                if (!empty($populated)) {
                    $fields[$key] = $populated;
                }
            }
        }
        // Recursively handle arrays (repeaters)
        elseif (is_array($value) && !empty($value)) {
            // Check if it's a numeric array (list of items)
            $isNumericArray = array_keys($value) === range(0, count($value) - 1);
            if ($isNumericArray) {
                // It's a repeater - process each item
                foreach ($value as $index => $item) {
                    if (is_array($item)) {
                        $fields[$key][$index] = populateNestedRelationships($item, $fieldsToPopulate, $db, $registry);
                    }
                }
            }
        }
    }

    return $fields;
}

/**
 * Fetch related content by ID (published only)
 */
function fetchRelatedContent(Database $db, ContentTypeRegistry $registry, int $id): ?array
{
    // Get content from database
    $content = $db->table('content')
        ->where('id', $id)
        ->where('status', 'published')
        ->first();

    if (!$content) {
        return null;
    }

    $type = $content['type'];

    if (!$registry->exists($type)) {
        return null;
    }

    $contentType = new ContentType($db, $type, $registry->get($type));
    $item = $contentType->find($id);

    if (!$item || $item['status'] !== 'published') {
        return null;
    }

    // Parse flexible_content if it's a JSON string
    if (isset($item['fields']['flexible_content']) && is_string($item['fields']['flexible_content'])) {
        $decoded = json_decode($item['fields']['flexible_content'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $item['fields']['flexible_content'] = $decoded;
        }
    }

    // Remove author_id from related content too
    unset($item['author_id']);

    return $item;
}
