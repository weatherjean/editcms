<?php
/** CLI only. php backup.php backup ROOT ARCHIVE | verify ARCHIVE | restore ARCHIVE NEW_ROOT */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
umask(0077);

function fail(string $message): never { throw new RuntimeException($message); }
function inside(string $path, string $root): bool { return $path === $root || str_starts_with($path, $root . '/'); }
function removeTree(string $path): void {
    if (is_dir($path) && !is_link($path)) {
        foreach (new FilesystemIterator($path) as $entry) removeTree($entry->getPathname());
        rmdir($path);
    } elseif (file_exists($path) || is_link($path)) unlink($path);
}
function validName(string $name): bool {
    if ($name === '' || str_contains($name, '\\') || str_contains($name, "\0") || str_starts_with($name, '/')) return false;
    foreach (explode('/', $name) as $part) if ($part === '' || $part === '.' || $part === '..') return false;
    return preg_match('#^(?:admin|admin-api|api|core|data|uploads)/#', $name) === 1
        || in_array($name, ['config.php','.htaccess','.user.ini','nginx.conf','index.html'], true);
}
function checkDatabase(string $path): void {
    $db = new SQLite3($path, SQLITE3_OPEN_READONLY);
    if ($db->querySingle('PRAGMA integrity_check') !== 'ok') fail('Database integrity check failed');
    $foreign = $db->query('PRAGMA foreign_key_check');
    if ($foreign->fetchArray()) fail('Database foreign key check failed');
    $db->close();
}
function extractVerified(string $archive, string $stage): array {
    $zip = new ZipArchive();
    if ($zip->open($archive, ZipArchive::RDONLY) !== true) fail('Cannot open archive');
    try {
        if ($zip->numFiles > 100001) fail('Archive has too many entries');
        $raw = $zip->getFromName('manifest.json', 16777217);
        if ($raw === false || strlen($raw) > 16777216) fail('Missing or oversized manifest');
        $manifest = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        if (($manifest['format'] ?? null) !== 1 || !is_array($manifest['files'] ?? null)) fail('Unsupported backup format');
        $seen = []; $total = 0;
        for ($i=0; $i<$zip->numFiles; $i++) {
            $stat = $zip->statIndex($i); $name = $stat['name'];
            if (isset($seen[$name])) fail('Duplicate archive entry');
            $seen[$name] = true;
            if ($name === 'manifest.json') continue;
            if (!validName($name) || !isset($manifest['files'][$name])) fail('Unexpected archive path');
            $zip->getExternalAttributesIndex($i, $opsys, $attributes);
            $kind = ($attributes >> 16) & 0170000;
            if ($kind !== 0 && $kind !== 0100000) fail('Archive contains non-regular files');
            $total += $stat['size'];
            if ($total > 10737418240) fail('Archive exceeds 10 GiB restore limit');
            $expected = $manifest['files'][$name];
            if (($expected['size'] ?? -1) !== $stat['size'] || !is_string($expected['sha256'] ?? null)) fail('Invalid archive manifest');
            $destination = $stage . '/' . $name;
            if (!is_dir(dirname($destination))) mkdir(dirname($destination), 0700, true);
            $input = $zip->getStream($name); $output = fopen($destination, 'xb');
            if (!$input || !$output) fail('Cannot extract archive file');
            $copied = stream_copy_to_stream($input, $output, $stat['size'] + 1);
            fclose($input); fclose($output);
            if ($copied !== $stat['size'] || !hash_equals($expected['sha256'], hash_file('sha256', $destination))) fail('Archive checksum mismatch');
        }
        if (count($seen) !== count($manifest['files']) + 1) fail('Archive is incomplete');
        foreach (['config.php','core/bootstrap.php','admin/index.html','api/index.php','admin-api/index.php'] as $required) {
            if (!isset($manifest['files'][$required])) fail('Missing required installation file');
        }
        $database = $manifest['database'] ?? '';
        if (!is_string($database) || !validName($database) || !isset($manifest['files'][$database]) || !str_starts_with($database, 'data/')) fail('Invalid database path');
        checkDatabase($stage . '/' . $database);
        return $manifest;
    } finally { $zip->close(); }
}
function backup(string $source, string $destination): void {
    $root = realpath($source);
    $parent = realpath(dirname($destination));
    if (!$root || !$parent || !is_dir($root) || inside($parent, dirname($root))) fail('Archive must be outside the website directory, in an existing directory');
    $destination = $parent . '/' . basename($destination);
    if (file_exists($destination) || is_link($destination)) fail('Archive already exists');
    if (!is_file($root . '/config.php') || !is_file($root . '/admin/index.html')) fail('Back up a deployed installation with config.php and built admin assets');
    $stage = sys_get_temp_dir() . '/editcms-backup-' . bin2hex(random_bytes(12));
    $lock = fopen($root . '/data/.operations.lock', 'c');
    if (!$lock) fail('Cannot open operation lock');
    if (!mkdir($stage, 0700)) { fclose($lock); fail('Cannot create staging directory'); }
    $temporary = $destination . '.partial-' . bin2hex(random_bytes(8));
    try {
        // Wait at most 30 seconds for existing API requests; new requests receive 503.
        $deadline = microtime(true) + 30;
        while (!flock($lock, LOCK_EX | LOCK_NB)) {
            if (microtime(true) > $deadline) fail('CMS is busy; retry backup later');
            usleep(100000);
        }
        define('EDIT_BASE_PATH', $root);
        require $root . '/config.php';
        $database = realpath(EDIT_DATABASE_PATH);
        if (!$database || !inside($database, $root . '/data')) fail('Database must be stored inside the installation data directory');
        $relativeDb = substr($database, strlen($root)+1);
        $snapshot = $stage . '/snapshot.sqlite';
        $sourceDb = new SQLite3($database, SQLITE3_OPEN_READONLY);
        $targetDb = new SQLite3($snapshot);
        if (!$sourceDb->backup($targetDb)) fail('SQLite snapshot failed');
        $targetDb->close(); $sourceDb->close();
        checkDatabase($snapshot);
        $zip = new ZipArchive();
        if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::EXCL) !== true) fail('Cannot create archive');
        $manifest = ['format'=>1,'created_at'=>gmdate(DATE_ATOM),'database'=>$relativeDb,'files'=>[]];
        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $entry) {
                $path = $entry->getPathname(); $name = substr($path, strlen($root)+1);
                if (!validName($name)) continue;
                if ($entry->isLink()) fail('Symlinks are not supported in backups');
                if (!$entry->isFile()) fail('Non-regular installation file');
                if (in_array($name, ['data/.operations.lock','data/.config.lock','data/config/.write.lock'], true) || in_array($name, [$relativeDb.'-wal',$relativeDb.'-shm',$relativeDb.'-journal'],true)) continue;
                $input = $name === $relativeDb ? $snapshot : $path;
                $manifest['files'][$name] = ['size'=>filesize($input),'sha256'=>hash_file('sha256',$input)];
                if (!$zip->addFile($input,$name)) fail('Cannot add archive file');
            }
            if (!$zip->addFromString('manifest.json',json_encode($manifest,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT))) fail('Cannot add manifest');
        } finally { if (!$zip->close()) fail('Cannot finalize archive'); }
        // Validate before publishing; all source writes remain blocked until ZIP closes.
        $check = $stage . '/verify'; mkdir($check,0700);
        extractVerified($temporary,$check);
        if (!link($temporary,$destination)) fail('Cannot publish archive without overwriting');
        unlink($temporary);
        echo "Backup verified and created: {$destination}\n";
    } finally {
        flock($lock,LOCK_UN); fclose($lock);
        if (is_file($temporary)) unlink($temporary);
        removeTree($stage);
    }
}
function restore(string $archive, string $destination): void {
    $parent = realpath(dirname($destination));
    if (!$parent || file_exists($destination) || is_link($destination)) fail('Restore requires a new destination in an existing parent directory');
    $destination = $parent . '/' . basename($destination);
    $stage = $parent . '/.editcms-restore-' . bin2hex(random_bytes(12));
    mkdir($stage,0700);
    try {
        $manifest = extractVerified($archive,$stage);
        // Never revive captured sessions, one-time CAPTCHA proofs, or email tokens.
        $db = new SQLite3($stage . '/' . $manifest['database']);
        $db->enableExceptions(true);
        $db->exec('BEGIN IMMEDIATE');
        foreach (['sessions','captcha_challenges','email_tokens','rate_limits'] as $table) {
            if ($db->querySingle("SELECT count(*) FROM sqlite_master WHERE type='table' AND name='{$table}'")) {
                if (!$db->exec("DELETE FROM {$table}")) fail('Cannot clear restored transient credentials');
            }
        }
        $db->exec("DELETE FROM settings WHERE key IN ('setup_token_hash','setup_token_expires')");
        $db->exec('COMMIT'); $db->close();
        foreach (['data/config/modules','data/config/field-groups','data/config/blocks','uploads'] as $directory) {
            if (!is_dir($stage.'/'.$directory)) mkdir($stage.'/'.$directory,0700,true);
        }
        if (file_put_contents($stage . '/data/.restored-database.json', json_encode(['database'=>$manifest['database']], JSON_THROW_ON_ERROR)) === false) fail('Cannot write restored database guard');
        if (file_exists($destination) || is_link($destination) || !rename($stage,$destination)) fail('Cannot publish restored installation');
        echo "Restored to {$destination}. Sessions revoked. Set web-server ownership/permissions and verify configuration before serving.\n";
    } finally { if (is_dir($stage)) removeTree($stage); }
}
try {
    if (!extension_loaded('zip') || !extension_loaded('sqlite3')) fail('PHP CLI requires zip and sqlite3 extensions');
    $command = $argv[1] ?? '';
    if ($command === 'backup' && $argc === 4) backup($argv[2],$argv[3]);
    elseif ($command === 'restore' && $argc === 4) restore($argv[2],$argv[3]);
    elseif ($command === 'verify' && $argc === 3) {
        $stage = sys_get_temp_dir().'/editcms-verify-'.bin2hex(random_bytes(12));mkdir($stage,0700);
        try { extractVerified($argv[2],$stage); echo "Backup verified\n"; } finally { removeTree($stage); }
    } else fail('Usage: php backup.php backup ROOT ARCHIVE | verify ARCHIVE | restore ARCHIVE NEW_ROOT');
} catch (Throwable $e) { fwrite(STDERR,'Operation failed: '.$e->getMessage()."\n"); exit(1); }
