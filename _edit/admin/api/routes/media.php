<?php

declare(strict_types=1);

use Edit\Core\Database\Database;

/**
 * Media management routes (requires auth)
 *
 * Routes:
 * - POST /media - Upload media file
 * - GET /media - List all media
 * - GET /media/:id - Get single media
 * - DELETE /media/:id - Delete media
 */
function handleMediaRoutes(string $method, string $path, Database $db, int $userId): bool
{
    // Upload media
    if ($path === '/media' && $method === 'POST') {
        if (!isset($_FILES['file'])) {
            sendError('No file uploaded', 400);
        }

        $file = $_FILES['file'];

        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'PHP extension stopped the upload'
            ];
            $errorMsg = $errorMessages[$file['error']] ?? 'Unknown upload error: ' . $file['error'];
            sendError($errorMsg, 400);
        }

        // Create upload directory structure
        $uploadDir = EDIT_BASE_PATH . '/uploads/' . date('Y/m');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        $uploadPath = $uploadDir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            sendError('Failed to save file', 500);
        }

        // Save to database
        $relativePath = date('Y/m') . '/' . $filename;
        $db->execute(
            "INSERT INTO media (filename, path, mime_type, size) VALUES (?, ?, ?, ?)",
            [$file['name'], $relativePath, $file['type'], $file['size']]
        );

        $mediaId = $db->lastInsertId();
        $media = $db->query("SELECT * FROM media WHERE id = ?", [$mediaId])[0];
        $media['url'] = '/_edit/uploads/' . $media['path'];

        sendJson($media);
        return true;
    }

    // Media list/get/delete endpoints
    if (preg_match('#^/media(/(\d+))?$#', $path, $mediaMatches)) {
        $mediaId = $mediaMatches[2] ?? null;

        if ($method === 'GET') {
            if ($mediaId) {
                // Get single media
                $media = $db->query("SELECT * FROM media WHERE id = ?", [$mediaId]);
                if (empty($media)) {
                    sendError('Media not found', 404);
                }
                $media[0]['url'] = '/_edit/uploads/' . $media[0]['path'];
                sendJson($media[0]);
            } else {
                // List all media
                $media = $db->query("SELECT * FROM media ORDER BY created_at DESC");
                foreach ($media as &$item) {
                    $item['url'] = '/_edit/uploads/' . $item['path'];
                }
                sendJson($media);
            }
            return true;
        }

        if ($method === 'DELETE' && $mediaId) {
            // Delete media file and database record
            $media = $db->query("SELECT * FROM media WHERE id = ?", [$mediaId]);
            if (!empty($media)) {
                $filePath = EDIT_BASE_PATH . '/uploads/' . $media[0]['path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                $db->execute("DELETE FROM media WHERE id = ?", [$mediaId]);
            }
            sendJson(['success' => true]);
            return true;
        }
    }

    return false;
}
