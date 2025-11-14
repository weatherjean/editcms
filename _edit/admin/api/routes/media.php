<?php

declare(strict_types=1);

use Edit\Core\Database\Database;
use Edit\Core\Security\Security;

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

        // Check upload error first
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

        // Validate file security (MIME type, size, content)
        $validation = Security::validateUpload($file);
        if (!$validation['valid']) {
            sendError($validation['error'], 400);
        }

        // Create upload directory structure with secure permissions
        $uploadDir = EDIT_BASE_PATH . '/uploads/' . date('Y/m');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0750, true);
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        $uploadPath = $uploadDir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            sendError('Failed to save file', 500);
        }

        // Save to database (use validated MIME type, not user-provided)
        $relativePath = date('Y/m') . '/' . $filename;
        $mediaId = $db->table('media')->insert([
            'filename' => $file['name'],
            'path' => $relativePath,
            'mime_type' => $validation['mime'], // Use validated MIME type
            'size' => $file['size']
        ]);

        $media = $db->table('media')->where('id', $mediaId)->first();
        $media = addMediaUrl($media);

        sendJson($media);
        return true;
    }

    // Media list/get/delete endpoints
    if (preg_match('#^/media(/(\d+))?$#', $path, $mediaMatches)) {
        $mediaId = $mediaMatches[2] ?? null;

        if ($method === 'GET') {
            if ($mediaId) {
                // Get single media
                $media = $db->table('media')->where('id', $mediaId)->first();
                if (!$media) {
                    sendError('Media not found', 404);
                }
                $media = addMediaUrl($media);
                sendJson($media);
            } else {
                // List all media
                $media = $db->table('media')->orderBy('created_at', 'DESC')->get();
                $media = addMediaUrl($media);
                sendJson($media);
            }
            return true;
        }

        if ($method === 'DELETE' && $mediaId) {
            // Check if media is in use before deleting
            $usage = $db->table('content_meta')
                ->where('meta_value', (string)$mediaId)
                ->first();

            if ($usage) {
                sendError('Media is currently in use and cannot be deleted', 400);
            }

            // Delete media file and database record
            $media = $db->table('media')->where('id', $mediaId)->first();
            if ($media) {
                $filePath = EDIT_BASE_PATH . '/uploads/' . $media['path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                $db->table('media')->where('id', $mediaId)->delete();
            }
            sendJson(['success' => true]);
            return true;
        }
    }

    return false;
}
