<?php

declare(strict_types=1);

use Edit\Core\Database\Database;
use Edit\Core\ContentTypes\ContentTypeRegistry;
use Edit\Core\ContentTypes\ContentType;

/**
 * Dynamic content CRUD routes (requires auth)
 *
 * Routes:
 * - GET /{type} - List all content of type
 * - GET /{type}/:id - Get single content item
 * - POST /{type} - Create new content item
 * - PUT /{type}/:id - Update content item
 * - DELETE /{type}/:id - Delete content item
 */
function handleContentRoutes(string $method, string $path, Database $db, ContentTypeRegistry $registry, int $userId): bool
{
    if (preg_match('#^/([a-z_-]+)(/(\d+))?$#', $path, $matches)) {
        $type = $matches[1];
        $id = isset($matches[3]) ? (int) $matches[3] : null;

        $reserved = [
            'auth', 'users', 'media', 'config', 'email-settings',
            'email-logs', 'send-email', 'post-types', 'field-groups',
            'blocks', 'health', 'public'
        ];

        if (in_array($type, $reserved)) {
            return false;
        }

        if (!$registry->exists($type)) {
            sendError("Content type '{$type}' not found", 404);
        }

        $contentType = new ContentType($db, $type, $registry->get($type));

        try {
            switch ($method) {
                case 'GET':
                    if ($id) {
                        $result = $contentType->find($id);
                        if (!$result) {
                            sendError('Content not found', 404);
                        }
                        sendJson($result);
                    } else {
                        $filters = [];
                        if (isset($_GET['status'])) {
                            $filters['status'] = $_GET['status'];
                        }
                        if (isset($_GET['limit'])) {
                            $filters['limit'] = (int) $_GET['limit'];
                        }
                        if (isset($_GET['offset'])) {
                            $filters['offset'] = (int) $_GET['offset'];
                        }

                        $result = $contentType->all($filters);
                        sendJson($result);
                    }
                    break;

                case 'POST':
                    $data = getJsonBody();
                    if (!$data) {
                        sendError('Invalid JSON data', 400);
                    }

                    $data['author_id'] = $userId;

                    $newId = $contentType->create($data);
                    $result = $contentType->find($newId);
                    sendJson($result, 201);
                    break;

                case 'PUT':
                    if (!$id) {
                        sendError('ID required for update', 400);
                    }

                    $data = getJsonBody();
                    if (!$data) {
                        sendError('Invalid JSON data', 400);
                    }

                    $contentType->update($id, $data);
                    $result = $contentType->find($id);
                    sendJson($result);
                    break;

                case 'DELETE':
                    if (!$id) {
                        sendError('ID required for delete', 400);
                    }

                    $contentType->delete($id);
                    sendJson(['success' => true]);
                    break;

                default:
                    sendError('Method not allowed', 405);
            }
        } catch (\Exception $e) {
            sendError($e->getMessage(), 500);
        }
        return true;
    }

    return false;
}
