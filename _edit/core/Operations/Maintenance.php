<?php

declare(strict_types=1);
namespace Edit\Core\Operations;

final class Maintenance
{
    private static $lock = null;

    /** Hold a shared lock for the entire request, including uploads/config writes. */
    public static function enter(string $root): void
    {
        $directory = $root . '/data';
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            http_response_code(503);
            exit('CMS storage unavailable');
        }
        self::$lock = @fopen($directory . '/.operations.lock', 'c');
        if (!self::$lock || !flock(self::$lock, LOCK_SH | LOCK_NB)) {
            header('Retry-After: 30');
            http_response_code(503);
            exit('CMS maintenance in progress. Please retry shortly.');
        }
    }
    /** A relocated config must never reconnect a restored site to the old database. */
    public static function checkRestoredDatabase(string $root, string $database): void
    {
        $marker = $root . '/data/.restored-database.json';
        if (!is_file($marker)) return;
        $expected = json_decode(file_get_contents($marker), true);
        $path = $expected['database'] ?? null;
        if (!is_string($path) || !str_starts_with($path, 'data/') || str_contains($path, '..')
            || realpath($database) === false || realpath($database) !== realpath($root . '/' . $path)) {
            http_response_code(503);
            exit('Restored database configuration needs correction before this installation can run.');
        }
    }

}
