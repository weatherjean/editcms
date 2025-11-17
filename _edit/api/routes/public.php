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
    $fieldGroupKeys = [];
    $allDefinedFields = [];

    foreach ($contentTypeConfig['field_groups'] as $group) {
        $fieldGroupKeys[] = $group['key'];
        foreach ($group['fields'] as $field) {
            $allDefinedFields[] = $field['key'];
        }
    }

    // Apply field_groups filter
    if (isset($query['field_groups'])) {
        $requestedGroups = array_map('trim', explode(',', $query['field_groups']));
        $allowedFields = [];

        foreach ($contentTypeConfig['field_groups'] as $group) {
            if (in_array($group['key'], $requestedGroups)) {
                foreach ($group['fields'] as $field) {
                    $allowedFields[] = $field['key'];
                }
            }
        }

        $fields = array_filter($fields, function($key) use ($allowedFields) {
            return in_array($key, $allowedFields);
        }, ARRAY_FILTER_USE_KEY);
    }

    // Apply exclude_open_fields
    if (isset($query['exclude_open_fields']) && $query['exclude_open_fields'] === 'true') {
        $fields = array_filter($fields, function($key) use ($allDefinedFields) {
            return in_array($key, $allDefinedFields);
        }, ARRAY_FILTER_USE_KEY);
    }

    // Apply fields_only
    if (isset($query['fields_only'])) {
        $requestedFields = array_map('trim', explode(',', $query['fields_only']));
        $fields = array_filter($fields, function($key) use ($requestedFields) {
            return in_array($key, $requestedFields);
        }, ARRAY_FILTER_USE_KEY);
    }

    $item['fields'] = $fields;
    return $item;
}

/**
 * Populate relationship fields
 */
function populateRelationships(array $item, ContentType $contentType, ContentTypeRegistry $registry, string $populateParam): array
{
    if (empty($populateParam)) {
        return $item;
    }

    $fieldsToPopulate = array_map('trim', explode(',', $populateParam));
    $db = $contentType->getDatabase();

    foreach ($fieldsToPopulate as $fieldKey) {
        if (!isset($item['fields'][$fieldKey])) {
            continue;
        }

        $value = $item['fields'][$fieldKey];

        // Handle both single relationships and arrays
        if (is_array($value)) {
            // Multiple relationships
            $populated = [];
            foreach ($value as $relatedId) {
                if (is_numeric($relatedId)) {
                    $related = fetchRelatedContent($db, $registry, (int) $relatedId);
                    if ($related) {
                        $populated[] = $related;
                    }
                }
            }
            $item['fields'][$fieldKey] = $populated;
        } elseif (is_numeric($value)) {
            // Single relationship
            $related = fetchRelatedContent($db, $registry, (int) $value);
            if ($related) {
                $item['fields'][$fieldKey] = $related;
            }
        }
    }

    return $item;
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

    // Remove author_id from related content too
    unset($item['author_id']);

    return $item;
}
