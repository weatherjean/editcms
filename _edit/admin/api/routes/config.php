<?php

declare(strict_types=1);

use Edit\Core\ContentTypes\ContentTypeRegistry;
use Edit\Core\ContentTypes\BlockRegistry;

/**
 * Helper function to validate JSON structure
 */
function validateConfigJson(string $json, string $type): ?string {
    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return 'Invalid JSON: ' . json_last_error_msg();
    }

    if ($type === 'module') {
        if (!isset($data['post_types']) || !is_array($data['post_types'])) {
            return 'Module must contain "post_types" array';
        }
        if (!isset($data['field_groups']) || !is_array($data['field_groups'])) {
            return 'Module must contain "field_groups" array';
        }
    } elseif ($type === 'field-group') {
        $required = ['key', 'title', 'locations', 'fields'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return "Field group must contain '{$field}' property";
            }
        }
    } elseif ($type === 'block') {
        $required = ['key', 'label', 'fields'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return "Block must contain '{$field}' property";
            }
        }
    }

    return null;
}

/**
 * Configuration management routes (requires auth)
 *
 * Routes:
 * - GET /post-types - List all post types
 * - GET /field-groups - List all field groups
 * - GET /blocks - List all blocks
 * - GET /config - List all config files
 * - GET /config/{type}/{name} - Get specific config file
 * - POST /config/{type} - Upload/replace config file
 * - DELETE /config/{type}/{name} - Delete config file
 * - GET /config/export - Export all config as ZIP
 * - POST /config/import - Import config from ZIP
 */
function handleConfigRoutes(string $method, string $path, ContentTypeRegistry $registry, BlockRegistry $blocks): bool
{
    // Get all active post types (for sidebar)
    if ($path === '/post-types' && $method === 'GET') {
        $postTypes = $registry->getPostTypes();
        sendJson($postTypes);
        return true;
    }

    // Get all active field groups
    if ($path === '/field-groups' && $method === 'GET') {
        $fieldGroups = $registry->getFieldGroups();
        sendJson($fieldGroups);
        return true;
    }

    // Get all blocks (for block editor)
    if ($path === '/blocks' && $method === 'GET') {
        sendJson($blocks->getBlocks());
        return true;
    }

    // List all config files by type
    if ($path === '/config' && $method === 'GET') {
        $configPath = EDIT_BASE_PATH . '/data/config';
        $result = [
            'modules' => [],
            'field_groups' => [],
            'blocks' => []
        ];

        // Get modules
        $modulesPath = $configPath . '/modules';
        if (is_dir($modulesPath)) {
            foreach (glob($modulesPath . '/*.json') as $file) {
                $result['modules'][] = [
                    'name' => basename($file, '.json'),
                    'filename' => basename($file),
                    'size' => filesize($file),
                    'modified' => filemtime($file)
                ];
            }
        }

        // Get field groups
        $fieldGroupsPath = $configPath . '/field-groups';
        if (is_dir($fieldGroupsPath)) {
            foreach (glob($fieldGroupsPath . '/*.json') as $file) {
                $result['field_groups'][] = [
                    'name' => basename($file, '.json'),
                    'filename' => basename($file),
                    'size' => filesize($file),
                    'modified' => filemtime($file)
                ];
            }
        }

        // Get blocks
        $blocksPath = $configPath . '/blocks';
        if (is_dir($blocksPath)) {
            foreach (glob($blocksPath . '/*.json') as $file) {
                $result['blocks'][] = [
                    'name' => basename($file, '.json'),
                    'filename' => basename($file),
                    'size' => filesize($file),
                    'modified' => filemtime($file)
                ];
            }
        }

        sendJson($result);
        return true;
    }

    // Get specific config file content
    if (preg_match('#^/config/(modules|field-groups|blocks)/([a-z0-9_-]+)$#', $path, $matches) && $method === 'GET') {
        $type = $matches[1];
        $name = $matches[2];
        $file = EDIT_BASE_PATH . "/data/config/{$type}/{$name}.json";

        if (!file_exists($file)) {
            sendError('Config file not found', 404);
        }

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        echo file_get_contents($file);
        exit;
    }

    // Upload/Replace config file
    if (preg_match('#^/config/(modules|field-groups|blocks)$#', $path, $matches) && $method === 'POST') {
        $type = $matches[1];
        $typeLabel = str_replace('-', ' ', $type);

        // Get uploaded file
        if (!isset($_FILES['file'])) {
            sendError('No file uploaded', 400);
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            sendError('File upload failed', 400);
        }

        // Validate file extension
        if (!str_ends_with($file['name'], '.json')) {
            sendError('File must be a JSON file', 400);
        }

        // Read and validate content
        $content = file_get_contents($file['tmp_name']);
        $validationType = $type === 'field-groups' ? 'field-group' : rtrim($type, 's');
        $error = validateConfigJson($content, $validationType);
        if ($error) {
            sendError($error, 400);
        }

        // Save file
        $targetPath = EDIT_BASE_PATH . "/data/config/{$type}";
        if (!is_dir($targetPath)) {
            mkdir($targetPath, 0755, true);
        }

        $targetFile = $targetPath . '/' . basename($file['name']);
        if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
            sendError('Failed to save file', 500);
        }

        // Reload configuration
        $registry->reload();
        $blocks->reload();

        sendJson([
            'success' => true,
            'message' => ucfirst($typeLabel) . ' uploaded successfully',
            'filename' => basename($file['name'])
        ]);
        return true;
    }

    // Delete config file
    if (preg_match('#^/config/(modules|field-groups|blocks)/([a-z0-9_-]+)$#', $path, $matches) && $method === 'DELETE') {
        $type = $matches[1];
        $name = $matches[2];
        $file = EDIT_BASE_PATH . "/data/config/{$type}/{$name}.json";

        if (!file_exists($file)) {
            sendError('Config file not found', 404);
        }

        if (!unlink($file)) {
            sendError('Failed to delete file', 500);
        }

        // Reload configuration
        $registry->reload();
        $blocks->reload();

        sendJson(['success' => true, 'message' => 'Config file deleted successfully']);
        return true;
    }

    // Export all configuration as ZIP
    if ($path === '/config/export' && $method === 'GET') {
        $configPath = EDIT_BASE_PATH . '/data/config';
        $zipFile = sys_get_temp_dir() . '/edit-config-' . time() . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            sendError('Failed to create ZIP archive', 500);
        }

        // Add all modules
        if (is_dir($configPath . '/modules')) {
            foreach (glob($configPath . '/modules/*.json') as $file) {
                $zip->addFile($file, 'modules/' . basename($file));
            }
        }

        // Add all field groups
        if (is_dir($configPath . '/field-groups')) {
            foreach (glob($configPath . '/field-groups/*.json') as $file) {
                $zip->addFile($file, 'field-groups/' . basename($file));
            }
        }

        // Add all blocks
        if (is_dir($configPath . '/blocks')) {
            foreach (glob($configPath . '/blocks/*.json') as $file) {
                $zip->addFile($file, 'blocks/' . basename($file));
            }
        }

        $zip->close();

        // Send file
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="edit-config-' . date('Y-m-d') . '.zip"');
        header('Content-Length: ' . filesize($zipFile));
        readfile($zipFile);
        unlink($zipFile);
        exit;
    }

    // Import all configuration from ZIP
    if ($path === '/config/import' && $method === 'POST') {
        if (!isset($_FILES['file'])) {
            sendError('No file uploaded', 400);
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            sendError('File upload failed', 400);
        }

        // Validate file is a ZIP
        if (!str_ends_with($file['name'], '.zip')) {
            sendError('File must be a ZIP archive', 400);
        }

        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) !== true) {
            sendError('Failed to open ZIP archive', 400);
        }

        $configPath = EDIT_BASE_PATH . '/data/config';

        // Create backup before importing
        $backupDir = $configPath . '/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $backupZip = new ZipArchive();
        $backupFile = $backupDir . '/' . time() . '.zip';

        if ($backupZip->open($backupFile, ZipArchive::CREATE) === true) {
            // Add all current modules
            if (is_dir($configPath . '/modules')) {
                foreach (glob($configPath . '/modules/*.json') as $file) {
                    $backupZip->addFile($file, 'modules/' . basename($file));
                }
            }

            // Add all current field groups
            if (is_dir($configPath . '/field-groups')) {
                foreach (glob($configPath . '/field-groups/*.json') as $file) {
                    $backupZip->addFile($file, 'field-groups/' . basename($file));
                }
            }

            // Add all current blocks
            if (is_dir($configPath . '/blocks')) {
                foreach (glob($configPath . '/blocks/*.json') as $file) {
                    $backupZip->addFile($file, 'blocks/' . basename($file));
                }
            }

            $backupZip->close();
        }

        $errors = [];
        $imported = ['modules' => 0, 'field_groups' => 0, 'blocks' => 0];

        // Extract and validate each file
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            // Determine type from path
            if (preg_match('#^(modules|field-groups|blocks)/([^/]+\.json)$#', $filename, $matches)) {
                $type = $matches[1];
                $basename = $matches[2];

                // Get file content
                $content = $zip->getFromIndex($i);
                if ($content === false) {
                    $errors[] = "Failed to read {$filename}";
                    continue;
                }

                // Validate JSON
                $validationType = $type === 'field-groups' ? 'field-group' : rtrim($type, 's');
                $error = validateConfigJson($content, $validationType);
                if ($error) {
                    $errors[] = "{$filename}: {$error}";
                    continue;
                }

                // Save file
                $targetDir = $configPath . '/' . $type;
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }

                $targetFile = $targetDir . '/' . $basename;
                if (file_put_contents($targetFile, $content) === false) {
                    $errors[] = "Failed to write {$filename}";
                    continue;
                }

                // Count successful imports
                $key = str_replace('-', '_', $type);
                $imported[$key]++;
            }
        }

        $zip->close();

        // Reload configuration
        $registry->reload();
        $blocks->reload();

        $backupFilename = basename($backupFile);

        if (!empty($errors)) {
            sendJson([
                'success' => true,
                'imported' => $imported,
                'errors' => $errors,
                'backup' => $backupFilename,
                'message' => 'Import completed with some errors. Backup saved to: ' . $backupFilename
            ]);
        } else {
            sendJson([
                'success' => true,
                'imported' => $imported,
                'backup' => $backupFilename,
                'message' => 'All configuration files imported successfully. Backup saved to: ' . $backupFilename
            ]);
        }
        return true;
    }

    return false;
}
