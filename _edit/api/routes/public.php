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

        if (!$registry->exists($type) || ($registry->get($type)['public'] ?? true) !== true) {
            sendError("Content type '{$type}' not found", 404);
        }

        $contentType = new ContentType($db, $type, $registry->get($type), $blocks, false);

        try {
            parsePublicQueryParams($_GET, $registry->get($type));
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
        } catch (\Throwable $e) {
            error_log('Public content request failed: ' . $e->getMessage());
            sendError('Unable to load content', 500);
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

    $item = serializePublicItem($item, $contentType, $registry);
    $item = applyFieldSelection($item, $registry, $_GET);


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
        $item = serializePublicItem($item, $contentType, $registry);
        $item = applyFieldSelection($item, $registry, $_GET);
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
    try {
        return \Edit\Core\ContentTypes\ContentQuery::parse($query, $contentTypeConfig);
    } catch (\InvalidArgumentException $e) {
        sendError($e->getMessage(), 400);
    }

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
                $fieldGroup = array_filter($fieldGroup, function ($fieldKey) use ($requestedFields) {
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

/** Both public entry points use the same schema-directed serializer. */
function serializePublicItem(array $item, ContentType $contentType, ContentTypeRegistry $registry): array
{
    global $blocks;
    $serializer = new \Edit\Core\ContentTypes\PublicSerializer($contentType->getDatabase(), $registry, $blocks);
    $populate = $_GET['populate'] ?? '';
    if (!is_string($populate)) {
        sendError('Invalid populate parameter', 400);
    }
    return $serializer->serialize($item, array_map('trim', explode(',', $populate))) ?? [];
}
