<?php

declare(strict_types=1);

$core = dirname(__DIR__) . '/_edit/core/';
spl_autoload_register(function ($class) use ($core) {
    $prefix = 'Edit\\Core\\';
    if (str_starts_with($class, $prefix)) {
        require $core . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

use Edit\Core\Configuration\Store;
use Edit\Core\Configuration\Validator;
use Edit\Core\ContentTypes\ContentTypeRegistry;
use Edit\Core\ContentTypes\BlockRegistry;

$path = dirname(__DIR__) . '/_edit/data/config';
Validator::validate(Store::files($path));
$registry = new ContentTypeRegistry($path);
$registry->load();
$blocks = new BlockRegistry($path);
$blocks->load();
foreach (['post', 'page', 'field_test'] as $type) {
    if (!$registry->exists($type)) {
        throw new RuntimeException("Example post type missing: {$type}");
    }
}
if (count($blocks->getBlocks()) !== 3) {
    throw new RuntimeException('Example blocks missing');
}
echo "PASS: repository example configuration validates and loads\n";
