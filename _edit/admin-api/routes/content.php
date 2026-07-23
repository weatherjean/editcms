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
 * - GET /{type}/:id/revisions - Get revisions for content item
 * - POST /{type}/:id/revisions/:revision_id/restore - Restore a revision
 */
function handleContentRoutes(string $method, string $path, Database $db, ContentTypeRegistry $registry, $blocks, int $userId): bool
{
    // Check for revision routes first
    if (preg_match('#^/([a-z_-]+)/(\d+)/revisions(/(\d+)/restore)?$#', $path, $matches)) {
        $type = $matches[1];
        $contentId = (int) $matches[2];
        $isRestore = !empty($matches[3]);
        $revisionId = $isRestore && isset($matches[4]) ? (int) $matches[4] : null;

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

        $contentType = new ContentType($db, $type, $registry->get($type), $blocks);

        try {
            if (!$contentType->find($contentId)) sendError('Content not found', 404);
            if ($isRestore && $method === 'POST') {
                // Restore revision
                if (!$revisionId) {
                    sendError('Revision ID required', 400);
                }
                $contentType->restoreRevision($contentId, $revisionId);
                $result = $contentType->find($contentId);
                sendJson($result);
            } elseif (!$isRestore && $method === 'GET') {
                // Get revisions
                $revisions = $contentType->getRevisions($contentId);
                sendJson($revisions);
            } else {
                sendError('Method not allowed', 405);
            }
        } catch (\OutOfBoundsException $e) {
            sendError($e->getMessage(), 404);
        } catch (\InvalidArgumentException $e) {
            sendError($e->getMessage(), $method === 'GET' ? 400 : 422);
        } catch (\Throwable $e) {
            error_log('Content request failed: ' . $e->getMessage());
            sendError('Unable to complete content request', 500);
        }
        return true;
    }

    // Regular content routes
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

        $contentType = new ContentType($db, $type, $registry->get($type), $blocks);

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
                        $filters = \Edit\Core\ContentTypes\ContentQuery::parse($_GET, $registry->get($type), false);
                        // Existing admin list screens load the whole collection.
                        if (!isset($_GET['limit'])) unset($filters['limit'], $filters['offset']);
                        if (isset($_GET['status'])) {
                            if (!in_array($_GET['status'], ['draft','published'], true)) sendError('Invalid status', 400);
                            $filters['status'] = $_GET['status'];
                        }

                        $result = $contentType->all($filters);
                        sendJson($result);
                    }
                    break;

                case 'POST':
                    $data = getContentJsonBody();
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

                    $data = getContentJsonBody();
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
        } catch (\OutOfBoundsException $e) {
            sendError($e->getMessage(), 404);
        } catch (\InvalidArgumentException $e) {
            sendError($e->getMessage(), $method === 'GET' ? 400 : 422);
        } catch (\Throwable $e) {
            error_log('Content request failed: ' . $e->getMessage());
            sendError('Unable to complete content request', 500);
        }
        return true;
    }

    return false;
}

/** Bound content requests before decoding; preserve field-specific 422 errors. */
function getContentJsonBody(): array
{
    $input = file_get_contents('php://input', false, null, 0, 2097153);
    if (strlen($input) > 2097152) sendError('Content request is too large', 413);
    try {
        $data = json_decode($input, true, 64, JSON_THROW_ON_ERROR);
    } catch (\JsonException $e) {
        sendError('Invalid JSON data', 400);
    }
    if (!is_array($data) || array_is_list($data)) sendError('Content request must be a JSON object', 400);
    return $data;
}
