<?php

declare(strict_types=1);

namespace Edit\Core\Configuration;

/** Immutable generations; one atomic pointer is the publication boundary. */
final class Store
{
    private static array $snapshots = [];

    public function __construct(private string $root)
    {
    }

    public static function resolve(string $root): string
    {
        return self::$snapshots[$root] ??= self::current($root);
    }

    private static function current(string $root): string
    {
        if (!is_file($root . '/.active.json')) {
            return $root;
        }
        $id = json_decode(file_get_contents($root . '/.active.json'), true)['generation'] ?? null;
        if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/', $id) || !is_dir($root . '/.versions/' . $id)) {
            throw new \RuntimeException('Invalid configuration generation');
        }
        return $root . '/.versions/' . $id;
    }

    public static function files(string $directory): array
    {
        $files = [];
        foreach (['modules', 'field-groups', 'blocks'] as $type) {
            foreach (glob($directory . '/' . $type . '/*.json') ?: [] as $file) {
                if (is_link($file) || !is_file($file)) {
                    throw new \RuntimeException('Config symlinks are not supported');
                }
                $value = file_get_contents($file);
                if ($value === false) {
                    throw new \RuntimeException('Cannot read configuration');
                }
                $files[$type . '/' . basename($file)] = $value;
            }
        }
        ksort($files);
        return $files;
    }

    public static function readZip(string $path): array
    {
        if (filesize($path) > 10485760) {
            throw new \InvalidArgumentException('Configuration ZIP exceeds 10 MiB');
        }
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            throw new \InvalidArgumentException('Invalid configuration ZIP');
        }
        try {
            if ($zip->numFiles > 1000) {
                throw new \InvalidArgumentException('Too many ZIP entries');
            }
            $files = [];
            $seen = [];
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'];
                if (isset($seen[$name])) {
                    throw new \InvalidArgumentException('Duplicate ZIP entry');
                }
                $seen[$name] = true;
                if (in_array($name, ['modules/', 'field-groups/', 'blocks/'], true)) {
                    continue;
                }
                if (!preg_match('#^(modules|field-groups|blocks)/[a-z0-9_-]+\.json$#', $name)) {
                    throw new \InvalidArgumentException('Unexpected ZIP path');
                }
                $zip->getExternalAttributesIndex($i, $system, $attributes);
                $kind = ($attributes >> 16) & 0170000;
                if ($kind !== 0 && $kind !== 0100000) {
                    throw new \InvalidArgumentException('ZIP contains non-regular files');
                }
                $total += $stat['size'];
                if ($total > 52428800 || $stat['size'] > 2097152) {
                    throw new \InvalidArgumentException('Configuration ZIP exceeds size limits');
                }
                $value = $zip->getFromIndex($i, 2097153);
                if ($value === false || strlen($value) !== $stat['size']) {
                    throw new \InvalidArgumentException('Invalid ZIP content');
                }
                $files[$name] = $value;
            }
            if (!$files) {
                throw new \InvalidArgumentException('No configuration files in ZIP');
            }
            return $files;
        } finally {
            $zip->close();
        }
    }

    public static function writeZip(string $path, array $files): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::EXCL) !== true) {
            throw new \RuntimeException('Cannot create configuration backup');
        }
        try {
            foreach (['modules', 'field-groups', 'blocks'] as $type) {
                $zip->addEmptyDir($type);
            }
            foreach ($files as $name => $json) {
                if (!$zip->addFromString($name, $json)) {
                    throw new \RuntimeException('Cannot write configuration backup');
                }
            }
        } finally {
            if (!$zip->close()) {
                throw new \RuntimeException('Cannot finalize configuration backup');
            }
        }
        // Empty configurations need a valid ZIP too.
        $verify = new \ZipArchive();
        if ($verify->open($path) !== true) {
            throw new \RuntimeException('Cannot verify configuration backup');
        }
        try {
            foreach ($files as $name => $json) {
                if ($verify->getFromName($name) !== $json) {
                    throw new \RuntimeException('Configuration backup verification failed');
                }
            }
        } finally {
            $verify->close();
        }
        chmod($path, 0600);
    }

    /** null values mean deletions; imports merge into the entire current registry. */
    public function apply(array $changes): array
    {
        if (!is_dir($this->root) && !mkdir($this->root, 0750, true)) {
            throw new \RuntimeException('Cannot create config directory');
        }
        $lock = fopen($this->root . '/.write.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) {
            throw new \RuntimeException('Cannot lock configuration');
        }
        $id = bin2hex(random_bytes(16));
        $directory = $this->root . '/.versions/' . $id;
        $pointer = $this->root . '/.pointer-' . $id;
        $published = false;
        try {
            $old = self::files(self::current($this->root));
            $next = $old;
            foreach ($changes as $name => $json) {
                if (!preg_match('#^(modules|field-groups|blocks)/[a-z0-9_-]+\.json$#', $name)) {
                    throw new \InvalidArgumentException('Invalid configuration path');
                }
                if ($json === null) {
                    if (!isset($next[$name])) {
                        throw new \OutOfBoundsException('Configuration file not found');
                    }unset($next[$name]);
                } elseif (!is_string($json) || strlen($json) > 2097152) {
                    throw new \InvalidArgumentException('Invalid or oversized configuration file');
                } else {
                    $next[$name] = $json;
                }
            }
            Validator::validate($next);
            foreach (['modules', 'field-groups', 'blocks'] as $type) {
                if (!mkdir($directory . '/' . $type, 0750, true)) {
                    throw new \RuntimeException('Cannot stage configuration');
                }
            }
            foreach ($next as $name => $json) {
                if (file_put_contents($directory . '/' . $name, $json) !== strlen($json)) {
                    throw new \RuntimeException('Cannot stage configuration file');
                }
            }
            if (self::files($directory) !== $this->sorted($next)) {
                throw new \RuntimeException('Staged configuration mismatch');
            }
            $backups = $this->root . '/backups';
            if (!is_dir($backups) && !mkdir($backups, 0750, true)) {
                throw new \RuntimeException('Cannot create configuration backup directory');
            }
            $backup = $id . '.zip';
            self::writeZip($backups . '/' . $backup, $old);
            if (file_put_contents($pointer, json_encode(['generation' => $id], JSON_THROW_ON_ERROR)) === false) {
                throw new \RuntimeException('Cannot stage configuration pointer');
            }
            if (!rename($pointer, $this->root . '/.active.json')) {
                throw new \RuntimeException('Cannot publish configuration');
            }
            $published = true;
            // This request retains its original reader snapshot; subsequent requests
            // resolve the new complete generation. Writers always read current().
            return ['success' => true, 'backup' => $backup, 'generation' => $id, 'message' => 'Configuration updated successfully'];
        } finally {
            if (is_file($pointer)) {
                unlink($pointer);
            }
            if (!$published && is_dir($directory)) {
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
                    $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
                }
                rmdir($directory);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function sorted(array $values): array
    {
        ksort($values);
        return $values;
    }
}
