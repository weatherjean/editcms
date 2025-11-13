<?php

declare(strict_types=1);

use Edit\Core\Database\Database;
use Edit\Core\ContentTypes\ContentTypeRegistry;
use Edit\Core\ContentTypes\BlockRegistry;

/**
 * Public query API routes (no auth required)
 *
 * Routes:
 * - GET /public/{type} - Query published content
 * - GET /public/{type}/{slug} - Get single published content by slug
 * - GET /public/docs - Get API documentation
 *
 * TODO: Implement full public query API with:
 * - Filtering (fields[key]=value, fields[key_gte]=value, etc.)
 * - Sorting (order_by, order_dir)
 * - Pagination (limit, offset)
 * - Field selection (fields_only, field_groups, exclude_open_fields)
 * - Relationship population (populate)
 */
function handlePublicRoutes(string $method, string $path, Database $db, ContentTypeRegistry $registry, BlockRegistry $blocks): bool
{
    // Serve API documentation
    if ($path === '/public/docs' && $method === 'GET') {
        $docsPath = EDIT_BASE_PATH . '/PUBLIC-API.md';
        if (file_exists($docsPath)) {
            header('Content-Type: text/markdown; charset=utf-8');
            echo file_get_contents($docsPath);
            exit;
        }
        sendError('API documentation not found', 404);
        return true;
    }

    // TODO: Implement public query routes
    // For now, just return false to let other routes handle it
    return false;
}
