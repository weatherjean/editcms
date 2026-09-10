<?php

declare(strict_types=1);

namespace Edit\Core\Auth;

final class Permissions
{
    public static function allows(string $role, string $method, string $path, array $contentTypes): bool
    {
        if ($role === 'admin') {
            return true;
        }
        if ($role !== 'editor') {
            return false;
        }
        if ($method === 'GET' && in_array($path, ['/auth/me', '/post-types', '/field-groups', '/blocks'], true)) {
            return true;
        }
        if (preg_match('#^/media(?:/\d+)?$#', $path)) {
            return in_array($method, ['GET', 'POST', 'DELETE'], true);
        }
        if (preg_match('#^/([a-z_-]+)(?:/\d+(?:/revisions(?:/\d+/restore)?)?)?$#', $path, $match)) {
            // Reserved routes can never acquire editorial permissions through config.
            if (in_array($match[1], ['auth', 'users', 'config', 'email-settings', 'email-logs', 'email-test', 'send-email', 'post-types', 'field-groups', 'blocks', 'health', 'public', 'media'], true)) {
                return false;
            }
            return in_array($match[1], $contentTypes, true) && in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true);
        }
        return false;
    }
}
