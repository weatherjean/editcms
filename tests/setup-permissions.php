<?php

declare(strict_types=1);
$core = dirname(__DIR__) . '/_edit/core/';
spl_autoload_register(function ($class) use ($core) {
    $prefix = 'Edit\\Core\\';
    if (str_starts_with($class, $prefix)) {
        require $core . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});
define('EDIT_SESSION_EXPIRY_HOURS', 24);
function dateTime($relative)
{
    return gmdate('Y-m-d H:i:s', strtotime($relative));
}
function check($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function rejected($fn)
{
    try {
        $fn();
    } catch (DomainException|InvalidArgumentException $e) {
        return;
    }throw new RuntimeException('Unexpected success');
}
use Edit\Core\Database\Database;
use Edit\Core\Auth\{Auth,Installation,Permissions};

$db = new Database(':memory:');
$setup = new Installation($db);
$auth = new Auth($db);
$claim = fn ($code) => $setup->claim($code, 'admin@example.invalid', 'FixturePassword789!', 'Admin');
rejected(fn () => $claim(''));
$first = $setup->issueToken();
$token = $setup->issueToken();
rejected(fn () => $claim($first));
check(!str_contains(json_encode($db->table('settings')->get()), $token), 'Raw setup token persisted');
$db->table('settings')->where('key', 'setup_token_expires')->update(['value' => '1']);
rejected(fn () => $claim($token));
$token = $setup->issueToken();
$admin = $claim($token);
rejected(fn () => $claim($token));
check($db->table('settings')->where('key', 'setup_token_hash')->count() === 0, 'Token not consumed');
try {
    $setup->issueToken();
    throw new LogicException('Claimed installation issued code');
} catch (RuntimeException $e) {
}
check($db->table('users')->where('id', $admin)->first()['role'] === 'admin', 'First user role');
$editor = $auth->register('editor@example.invalid', 'FixturePassword789!', 'Editor', 'editor');
$editorToken = $auth->login('editor@example.invalid', 'FixturePassword789!')['token'];
rejected(fn () => $auth->changeRole($admin, 'editor'));
rejected(fn () => $auth->deleteUser($admin));
$auth->changeRole($editor, 'admin');
check($auth->verifyToken($editorToken) === null, 'Role change did not revoke sessions');
$auth->changeRole($admin, 'editor');
rejected(fn () => $auth->changeRole($editor, 'editor'));
rejected(fn () => $auth->deleteUser($editor));
$auth->deleteUser($admin);
check($db->table('users')->count() === 1, 'Account deletion failed');
foreach ([['GET', '/users'], ['PUT', '/users/1'], ['PUT', '/users/1/role'], ['POST', '/users'], ['DELETE', '/users/1'], ['GET', '/email-settings'], ['POST', '/email-test'], ['GET', '/email-logs'], ['POST', '/config'], ['GET', '/config/export'], ['POST', '/config/import']] as [$method,$path]) {
    check(!Permissions::allows('editor', $method, $path, ['property', 'users', 'config', 'email-test']), 'Editor bypass: ' . $path);
}
foreach ([['GET', '/post-types'], ['GET', '/field-groups'], ['GET', '/blocks'], ['GET', '/auth/me'], ['POST', '/property'], ['PUT', '/property/1'], ['DELETE', '/property/1'], ['POST', '/property/1/revisions/1/restore'], ['POST', '/media']] as [$method,$path]) {
    check(Permissions::allows('editor', $method, $path, ['property']), 'Editor workflow blocked: ' . $path);
}
check(!Permissions::allows('unknown', 'GET', '/property', ['property']), 'Unknown role allowed');
// Upgrade an old database without roles and preserve the existing account.
$file = tempnam(sys_get_temp_dir(), 'edit-role-');
try {
    $old = new Database($file);
    $old->table('users')->insert(['email' => 'legacy@example.invalid', 'password' => 'fixture', 'name' => 'Legacy']);
    $old->execute('ALTER TABLE users DROP COLUMN role');
    unset($old);
    $upgraded = new Database($file);
    check($upgraded->table('users')->first()['role'] === 'admin', 'Legacy user lost privileges');
    unset($upgraded);
} finally {
    unlink($file);
}
echo "PASS: setup code hashing/rotation/expiry/single use, first admin, role permissions, last-admin protection, revocation and legacy migration\n";
