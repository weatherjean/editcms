<?php

declare(strict_types=1);
$core = dirname(__DIR__) . '/_edit/core/';
spl_autoload_register(function ($class) use ($core) {
    $prefix = 'Edit\\Core\\';
    if (str_starts_with($class, $prefix)) {
        require $core . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});
use Edit\Core\Database\Database;
use Edit\Core\ContentTypes\{ContentType, ContentTypeRegistry, BlockRegistry, PublicSerializer};
use Edit\Core\Security\Security;

function check($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function addMediaUrl($row)
{
    $row['url'] = '/_edit/uploads/' . $row['path'];
    return $row;
}
$db = new Database(':memory:');
$registry = new ContentTypeRegistry('/nonexistent-fixture');
$registry->load();
$fields = [
    ['key' => 'title', 'type' => 'text'], ['key' => 'secret', 'type' => 'text', 'public' => false],
    ['key' => 'related', 'type' => 'relationship'], ['key' => 'account', 'type' => 'relationship', 'target' => 'user'],
    ['key' => 'rows', 'type' => 'repeater', 'config' => ['fields' => [
        ['key' => 'related', 'type' => 'relationship'], ['key' => 'bedrooms', 'type' => 'number'],
        ['key' => 'account', 'type' => 'relationship', 'target' => 'user'], ['key' => 'secret', 'type' => 'text', 'public' => false],
    ]]],
];
$schema = ['key' => 'property', 'fields' => array_column($fields, null, 'key'), 'field_groups' => [['key' => 'details', 'fields' => $fields], ['key' => 'private', 'public' => false, 'fields' => [['key' => 'note', 'type' => 'text']]]]];
(new ReflectionProperty($registry, 'contentTypes'))->setValue($registry, ['property' => $schema, 'private' => array_merge($schema, ['public' => false])]);
$blocks = new BlockRegistry('/nonexistent-fixture');
$blocks->load();
(new ReflectionProperty($blocks, 'blocks'))->setValue($blocks, ['features' => ['key' => 'features', 'fields' => $fields]]);
$insert = fn ($type, $slug, $status) => $db->table('content')->insert(['type' => $type, 'slug' => $slug, 'status' => $status]);
$public = $insert('property', 'public', 'published');
$draft = $insert('property', 'secret-draft', 'draft');
$private = $insert('private', 'private-published', 'published');
$db->table('media')->insert(['filename' => 'image.jpg', 'path' => '2026/09/image.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);
$db->table('content_meta')->insert(['content_id' => $public, 'meta_key' => 'details.secret', 'meta_value' => 'DO NOT EXPOSE']);
$db->table('content_meta')->insert(['content_id' => $public, 'meta_key' => 'details.related', 'meta_value' => (string)$draft]);
$serializer = new PublicSerializer($db, $registry, $blocks);
$item = ['id' => $public, 'type' => 'property', 'slug' => 'public', 'status' => 'published', 'author_id' => 7, 'fields' => [
    'details' => ['title' => 'Visible', 'secret' => 'DO NOT EXPOSE', 'related' => $draft, 'account' => 1, 'rows' => [['related' => $draft, 'bedrooms' => 1, 'secret' => 'DO NOT EXPOSE', 'account' => 1]]],
    'private' => ['note' => 'DO NOT EXPOSE'], 'unknown' => 'DO NOT EXPOSE',
    'flexible_content' => [['block_type' => 'features', 'fields' => ['related' => $draft, 'secret' => 'DO NOT EXPOSE', 'rows' => [['bedrooms' => 1, 'related' => $draft]]]], ['block_type' => 'unknown', 'fields' => ['secret' => 'DO NOT EXPOSE']]],
]];
foreach ([[], ['related', 'bedrooms', 'account', 'secret']] as $populate) {
    $out = $serializer->serialize($item, $populate);
    check(!str_contains(json_encode($out), 'DO NOT EXPOSE'), 'Private and unknown fields leaked');
    check(!str_contains(json_encode($out), 'secret-draft'), 'Draft slug leaked');
    check(!isset($out['author_id']) && !isset($out['fields']['details']['account']), 'Account information leaked');
    check($out['fields']['details']['related'] === null, 'Draft ID leaked');
    check($out['fields']['details']['rows'][0]['related'] === null, 'Nested draft leaked');
    check($out['fields']['flexible_content'][0]['fields']['rows'][0]['bedrooms'] === 1, 'Number became media');
}
$item['fields']['details']['related'] = $private;
check($serializer->serialize($item)['fields']['details']['related'] === null, 'Private type relationship leaked');
$item['fields']['details']['related'] = $public;
$out = $serializer->serialize($item, ['related']);
check($out['fields']['details']['related']['fields']['details']['related'] === null, 'Expanded content leaked draft');
check(!isset($out['fields']['details']['related']['fields']['details']['secret']), 'Expanded private field leaked');
check($serializer->serialize(array_merge($item, ['status' => 'draft'])) === null, 'Root draft leaked');
check($serializer->serialize(array_merge($item, ['type' => 'private'])) === null, 'Root private type leaked');
check(Security::uploadExtension('image/jpeg') === 'jpg', 'JPEG extension');
check(Security::uploadExtension('image/svg+xml') === null, 'SVG must be rejected');
check(Security::uploadExtension('text/x-php') === null, 'PHP must be rejected');
$type = new ContentType($db, 'property', $schema, $blocks, false);
check($type->find($public)['fields']['details']['related'] === $draft, 'Raw hydration changed IDs');
// Exercise both route aliases in separate PHP processes using this fixture.
require dirname(__DIR__) . '/_edit/' . ($argv[1] ?? 'api') . '/routes/public.php';
$_GET = ['populate' => 'related'];
$out = getPublicContentBySlug($type, $registry, 'public');
check($out['fields']['details']['related'] === null, 'Route draft exposure');
check(!isset($out['fields']['details']['secret']), 'Route private exposure');
check(count(getPublicContentList($type, $registry, 'property')['data']) === 1, 'Published list');
echo "PASS: public serialization, nested relationships, visibility, typed population, upload extensions and " . ($argv[1] ?? 'api') . " routes\n";
