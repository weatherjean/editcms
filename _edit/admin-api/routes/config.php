<?php

declare(strict_types=1);
use Edit\Core\ContentTypes\ContentTypeRegistry;
use Edit\Core\ContentTypes\BlockRegistry;
use Edit\Core\Configuration\Store;

function handleConfigRoutes(string $method, string $path, ContentTypeRegistry $registry, BlockRegistry $blocks): bool
{
    if ($method === 'GET') {
        if ($path === '/post-types') {
            sendJson($registry->getPostTypes());
        }
        if ($path === '/field-groups') {
            sendJson($registry->getFieldGroups());
        }
        if ($path === '/blocks') {
            sendJson($blocks->getBlocks());
        }
    }
    if ($path !== '/config' && !str_starts_with($path, '/config/')) {
        return false;
    }
    $root = EDIT_BASE_PATH . '/data/config';
    $active = Store::resolve($root);
    try {
        if ($path === '/config' && $method === 'GET') {
            sendJson(['modules' => listConfigFiles($active . '/modules'), 'field_groups' => listConfigFiles($active . '/field-groups'), 'blocks' => listConfigFiles($active . '/blocks')]);
        }
        if ($path === '/config/export' && $method === 'GET') {
            $temporary = sys_get_temp_dir() . '/edit-config-' . bin2hex(random_bytes(16)) . '.zip';
            try {
                Store::writeZip($temporary, Store::files($active));
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="edit-config-' . date('Y-m-d') . '.zip"');
                header('Content-Length: ' . filesize($temporary));
                readfile($temporary);
            } finally {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }
            exit;
        }
        if ($path === '/config/import' && $method === 'POST') {
            $file = configUpload('zip');
            $changes = Store::readZip($file['tmp_name']);
            $result = (new Store($root))->apply($changes);
            $result['imported'] = ['modules' => 0, 'field_groups' => 0, 'blocks' => 0];
            foreach (array_keys($changes) as $name) {
                $result['imported'][str_replace('-', '_', explode('/', $name)[0])]++;
            }
            sendJson($result);
        }
        if (preg_match('#^/config/(modules|field-groups|blocks)$#', $path, $matches) && $method === 'POST') {
            $file = configUpload('json');
            if (!preg_match('/^[a-z0-9_-]+\.json$/', $file['name'])) {
                throw new \InvalidArgumentException('Invalid configuration filename');
            }
            $json = file_get_contents($file['tmp_name'], false, null, 0, 2097153);
            if ($json === false) {
                throw new \RuntimeException('Cannot read upload');
            }
            $result = (new Store($root))->apply([$matches[1] . '/' . $file['name'] => $json]);
            sendJson($result + ['filename' => $file['name']]);
        }
        if (preg_match('#^/config/(modules|field-groups|blocks)/([a-z0-9_-]+)$#', $path, $matches)) {
            $name = $matches[1] . '/' . $matches[2] . '.json';
            if ($method === 'GET') {
                if (!is_file($active . '/' . $name)) {
                    throw new \OutOfBoundsException('Config file not found');
                }
                header('Content-Type: application/json');
                echo file_get_contents($active . '/' . $name);
                exit;
            }
            if ($method === 'DELETE') {
                sendJson((new Store($root))->apply([$name => null]));
            }
        }
    } catch (\OutOfBoundsException $e) {
        sendError($e->getMessage(), 404);
    } catch (\InvalidArgumentException $e) {
        sendError($e->getMessage(), 400);
    } catch (\Throwable $e) {
        error_log('Configuration update failed: ' . $e->getMessage());
        sendError('Unable to complete configuration operation', 500);
    }
    return false;
}
function configUpload(string $extension): array
{
    $file = $_FILES['file'] ?? null;
    if (!is_array($file) || ($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($file['name'] ?? null) || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
        throw new \InvalidArgumentException('Invalid configuration upload');
    }
    if (!str_ends_with($file['name'], '.' . $extension)) {
        throw new \InvalidArgumentException('Unexpected upload extension');
    }
    return $file;
}
