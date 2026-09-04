<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$mode = $argv[1] ?? 'preflight';
if (!in_array($mode, ['preflight','test','up','verify','roundtrip'], true)) throw new InvalidArgumentException('사용법: php tools/apply_main_settings_domain_key_normalization.php [preflight|test|up|verify|roundtrip]');
$db = DbPdo::conn();
$old = ['web.settings.statutory_standards.manage','web.settings.base-info.brand_logo','web.settings.base-info.clients','web.settings.base-info.projects','web.settings.base-info.accounts','web.settings.base-info.cards','web.settings.organization.employees','web.settings.organization.departments','web.settings.organization.positions','web.settings.organization.roles','web.settings.organization.role_permissions','web.settings.organization.approval'];
$new = ['web.settings.standard.statutory-standard','web.settings.base-info.brand','web.settings.base-info.client','web.settings.base-info.project','web.settings.base-info.bank-account','web.settings.base-info.card','web.settings.organization.employee','web.settings.organization.department','web.settings.organization.position','web.settings.organization.role','web.settings.organization.permission-assignment','web.settings.organization.approval-template'];
$quoted = static fn(array $keys): string => implode(',', array_map(fn(string $key): string => $db->quote($key), $keys));
$snapshot = static function () use ($db, $old, $new, $quoted): array {
    $oldSql = $quoted($old); $newSql = $quoted($new);
    $legacyRegistrySql = "SELECT page_key, default_route_key, default_route_url FROM system_page_registry WHERE page_key LIKE 'settings.%' AND default_route_url REGEXP '/(brand-logo|clients|projects|bank-accounts|cards|work-teams|employees|departments|positions|roles|approval)$' ORDER BY page_key";
    return [
        'permissions' => (int)$db->query('SELECT COUNT(*) FROM auth_permissions')->fetchColumn(),
        'old_keys' => (int)$db->query("SELECT COUNT(*) FROM auth_permissions WHERE permission_key IN ({$oldSql})")->fetchColumn(),
        'canonical_keys' => (int)$db->query("SELECT COUNT(*) FROM auth_permissions WHERE permission_key IN ({$newSql})")->fetchColumn(),
        'role_mappings' => (int)$db->query("SELECT COUNT(*) FROM auth_role_permissions rp JOIN auth_permissions p ON p.id=rp.permission_id WHERE p.permission_key IN ({$oldSql},{$newSql})")->fetchColumn(),
        'user_mappings' => (int)$db->query("SELECT COUNT(*) FROM auth_user_permissions up JOIN auth_permissions p ON p.id=up.permission_id WHERE p.permission_key IN ({$oldSql},{$newSql})")->fetchColumn(),
        'legacy_registry_urls' => (int)$db->query("SELECT COUNT(*) FROM ({$legacyRegistrySql}) legacy_registry")->fetchColumn(),
        'legacy_registry_rows' => $db->query($legacyRegistrySql)->fetchAll(PDO::FETCH_ASSOC),
    ];
};
$execute = static function (string $direction) use ($db): void {
    $path = PROJECT_ROOT.'/app/migrations/20260904_04_normalize_main_settings_domain_keys.'.$direction.'.sql';
    foreach (array_filter(array_map('trim', explode(';', (string)file_get_contents($path)))) as $sql) $db->exec($sql);
};
if (in_array($mode, ['preflight','verify'], true)) {
    $state=$snapshot(); $passed=$mode==='preflight'||($state['old_keys']===0&&$state['canonical_keys']===12&&$state['legacy_registry_urls']===0);
    echo json_encode(['mode'=>$mode,'passed'=>$passed,'state'=>$state],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL; exit($passed?0:1);
}
$assertSameState = static function (array $expected, array $actual): void {
    foreach (['permissions','old_keys','canonical_keys','role_mappings','user_mappings','legacy_registry_urls'] as $key) {
        if ($actual[$key] !== $expected[$key]) throw new RuntimeException("Roundtrip 불변식 실패: {$key}");
    }
};
$db->beginTransaction();
try {
    $before=$snapshot();
    if ($mode === 'roundtrip') {
        $execute('down'); $down=$snapshot(); $execute('up'); $after=$snapshot(); $assertSameState($before, $after); $db->rollBack();
        echo json_encode(['mode'=>$mode,'before'=>$before,'down'=>$down,'after'=>$after,'rolled_back'=>true],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL; exit(0);
    }
    $execute('up'); $after=$snapshot();
    if($after['permissions']!==$before['permissions']||$after['role_mappings']!==$before['role_mappings']||$after['user_mappings']!==$before['user_mappings']||$after['canonical_keys']!==12||$after['legacy_registry_urls']!==0) throw new RuntimeException('권한 ID·Mapping·Route URL 보존 불변식을 충족하지 못했습니다: '.json_encode(['before'=>$before,'after'=>$after],JSON_UNESCAPED_UNICODE));
    if($mode==='test')$db->rollBack();else$db->commit(); echo json_encode(['mode'=>$mode,'before'=>$before,'after'=>$after,'rolled_back'=>$mode==='test'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
} catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
