<?php

declare(strict_types=1);

use Edit\Core\ContentTypes\ContentTypeRegistry;
use Edit\Core\ContentTypes\BlockRegistry;
use Edit\Core\Security\Security;

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
 * Helper function to convert config type to validation type
 * 'modules' => 'module', 'field-groups' => 'field-group', 'blocks' => 'block'
 */
function getValidationType(string $configType): string {
    return $configType === 'field-groups' ? 'field-group' : rtrim($configType, 's');
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
    if ($path === '/post-types' && $method === 'GET') {
        $postTypes = $registry->getPostTypes();
        sendJson($postTypes);
        return true;
    }

    if ($path === '/field-groups' && $method === 'GET') {
        $fieldGroups = $registry->getFieldGroups();
        sendJson($fieldGroups);
        return true;
    }

    if ($path === '/blocks' && $method === 'GET') {
        sendJson($blocks->getBlocks());
        return true;
    }

    if ($path === '/config' && $method === 'GET') {
        $configPath = EDIT_BASE_PATH . '/data/config';
        $result = [
            'modules' => listConfigFiles($configPath . '/modules'),
            'field_groups' => listConfigFiles($configPath . '/field-groups'),
            'blocks' => listConfigFiles($configPath . '/blocks')
        ];

        sendJson($result);
        return true;
    }

    if (preg_match('#^/config/(modules|field-groups|blocks)/([a-z0-9_-]+)$#', $path, $matches) && $method === 'GET') {
        $type = $matches[1];
        $name = $matches[2];
        $file = EDIT_BASE_PATH . "/data/config/{$type}/{$name}.json";

        if (!file_exists($file)) {
            sendError('Config file not found', 404);
        }

        header('Content-Type: application/json');
        echo file_get_contents($file);
        exit;
    }

    if (preg_match('#^/config/(modules|field-groups|blocks)$#', $path, $matches) && $method === 'POST') {
        $type = $matches[1];
        $typeLabel = str_replace('-', ' ', $type);

        if (!isset($_FILES['file'])) {
            sendError('No file uploaded', 400);
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            sendError('File upload failed', 400);
        }

        if (!str_ends_with($file['name'], '.json')) {
            sendError('File must be a JSON file', 400);
        }

        $content = file_get_contents($file['tmp_name']);
        $validationType = getValidationType($type);
        $error = validateConfigJson($content, $validationType);
        if ($error) {
            sendError($error, 400);
        }

        $targetPath = EDIT_BASE_PATH . "/data/config/{$type}";
        if (!is_dir($targetPath)) {
            mkdir($targetPath, 0750, true);
        }

        $targetFile = $targetPath . '/' . basename($file['name']);
        if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
            sendError('Failed to save file', 500);
        }

        $registry->reload();
        $blocks->reload();

        sendJson([
            'success' => true,
            'message' => ucfirst($typeLabel) . ' uploaded successfully',
            'filename' => basename($file['name'])
        ]);
        return true;
    }

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

        $registry->reload();
        $blocks->reload();

        sendJson(['success' => true, 'message' => 'Config file deleted successfully']);
        return true;
    }

    if ($path === '/config/export' && $method === 'GET') {
        $configPath = EDIT_BASE_PATH . '/data/config';
        $zipFile = sys_get_temp_dir() . '/edit-config-' . time() . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            sendError('Failed to create ZIP archive', 500);
        }

        if (is_dir($configPath . '/modules')) {
            foreach (glob($configPath . '/modules/*.json') as $file) {
                $zip->addFile($file, 'modules/' . basename($file));
            }
        }

        if (is_dir($configPath . '/field-groups')) {
            foreach (glob($configPath . '/field-groups/*.json') as $file) {
                $zip->addFile($file, 'field-groups/' . basename($file));
            }
        }

        if (is_dir($configPath . '/blocks')) {
            foreach (glob($configPath . '/blocks/*.json') as $file) {
                $zip->addFile($file, 'blocks/' . basename($file));
            }
        }

        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="edit-config-' . date('Y-m-d') . '.zip"');
        header('Content-Length: ' . filesize($zipFile));
        readfile($zipFile);
        unlink($zipFile);
        exit;
    }

    if ($path === '/config/import' && $method === 'POST') {
        if (!isset($_FILES['file'])) {
            sendError('No file uploaded', 400);
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            sendError('File upload failed', 400);
        }

        if (!str_ends_with($file['name'], '.zip')) {
            sendError('File must be a ZIP archive', 400);
        }

        $zipValidation = Security::validateZIP($file['tmp_name']);
        if (!$zipValidation['valid']) {
            sendError($zipValidation['error'], 400);
        }

        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) !== true) {
            sendError('Failed to open ZIP archive', 400);
        }

        $configPath = EDIT_BASE_PATH . '/data/config';

        $backupDir = $configPath . '/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $backupZip = new ZipArchive();
        $backupFile = $backupDir . '/' . time() . '.zip';

        if ($backupZip->open($backupFile, ZipArchive::CREATE) === true) {
            if (is_dir($configPath . '/modules')) {
                foreach (glob($configPath . '/modules/*.json') as $file) {
                    $backupZip->addFile($file, 'modules/' . basename($file));
                }
            }

            if (is_dir($configPath . '/field-groups')) {
                foreach (glob($configPath . '/field-groups/*.json') as $file) {
                    $backupZip->addFile($file, 'field-groups/' . basename($file));
                }
            }

            if (is_dir($configPath . '/blocks')) {
                foreach (glob($configPath . '/blocks/*.json') as $file) {
                    $backupZip->addFile($file, 'blocks/' . basename($file));
                }
            }

            $backupZip->close();
        }

        $errors = [];
        $imported = ['modules' => 0, 'field_groups' => 0, 'blocks' => 0];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            if (preg_match('#^(modules|field-groups|blocks)/([^/]+\.json)$#', $filename, $matches)) {
                $type = $matches[1];
                $basename = $matches[2];

                $content = $zip->getFromIndex($i);
                if ($content === false) {
                    $errors[] = "Failed to read {$filename}";
                    continue;
                }

                $validationType = getValidationType($type);
                $error = validateConfigJson($content, $validationType);
                if ($error) {
                    $errors[] = "{$filename}: {$error}";
                    continue;
                }

                $targetDir = $configPath . '/' . $type;
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }

                $targetFile = $targetDir . '/' . $basename;
                if (file_put_contents($targetFile, $content) === false) {
                    $errors[] = "Failed to write {$filename}";
                    continue;
                }

                $key = str_replace('-', '_', $type);
                $imported[$key]++;
            }
        }

        $zip->close();

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
