<?php
namespace Core\Helpers;

final class PermissionSourceHelper
{
    public static function resolve(array $permission): string
    {
        $source = strtolower(trim((string) ($permission['permission_source'] ?? '')));
        if (in_array($source, ['web', 'api'], true)) return $source;

        $permissionKey = strtolower(trim((string) ($permission['permission_key'] ?? '')));
        return str_starts_with($permissionKey, 'web.') ? 'web' : 'api';
    }
}
