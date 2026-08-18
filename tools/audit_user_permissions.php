<?php
declare(strict_types=1);
define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';
use Core\DbPdo;
use App\Repositories\Auth\UserPermissionRepository;
$pdo=DbPdo::conn();$scalar=static fn(string $sql):int=>(int)$pdo->query($sql)->fetchColumn();
$result=[
 'users'=>$scalar('SELECT COUNT(*) FROM auth_users'),
 'profiles'=>$scalar('SELECT COUNT(*) FROM auth_user_permission_profiles'),
 'role_profiles'=>$scalar("SELECT COUNT(*) FROM auth_user_permission_profiles WHERE permission_mode='ROLE'"),
 'user_permissions'=>$scalar('SELECT COUNT(*) FROM auth_user_permissions'),
 'audits'=>$scalar('SELECT COUNT(*) FROM auth_user_permission_audits'),
 'role_permissions'=>$scalar('SELECT COUNT(*) FROM auth_role_permissions'),
 'legacy_tables'=>$scalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('auth_user_permission_overrides','auth_user_permission_override_audits')"),
 'new_tables'=>$scalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('auth_user_permission_profiles','auth_user_permissions','auth_user_permission_audits')"),
 'canonical_permissions'=>$scalar("SELECT COUNT(*) FROM auth_permissions WHERE permission_key IN ('api.settings.user_permission.list','api.settings.user_permission.detail','api.settings.user_permission.save')"),
 'duplicate_keys'=>$scalar("SELECT COUNT(*) FROM (SELECT permission_key FROM auth_permissions GROUP BY permission_key HAVING COUNT(*)>1) x"),
 'profile_orphans'=>$scalar('SELECT COUNT(*) FROM auth_user_permission_profiles x LEFT JOIN auth_users u ON u.id=x.user_id WHERE u.id IS NULL'),
 'mapping_user_orphans'=>$scalar('SELECT COUNT(*) FROM auth_user_permissions x LEFT JOIN auth_users u ON u.id=x.user_id WHERE u.id IS NULL'),
 'mapping_permission_orphans'=>$scalar('SELECT COUNT(*) FROM auth_user_permissions x LEFT JOIN auth_permissions p ON p.id=x.permission_id WHERE p.id IS NULL'),
];
$result['role_effective_mismatch']=$scalar("SELECT COUNT(*) FROM auth_users u JOIN auth_user_permission_profiles pr ON pr.user_id=u.id WHERE pr.permission_mode<>'ROLE'");
$repository=new UserPermissionRepository($pdo);$result['effective_set_mismatch']=0;
foreach($repository->listUsers() as $user){
 if((int)$user['approved']!==1||(int)$user['is_active']!==1||(int)$user['role_active']!==1)continue;
 $role=$repository->rolePermissionIds((string)$user['role_id']);$effective=array_keys($repository->effectivePermissionSet((string)$user['user_id']));sort($role);sort($effective);
 if($role!==$effective)$result['effective_set_mismatch']++;
}
foreach(['super_admin','admin'] as $role)$result[$role.'_mapping']=$scalar("SELECT COUNT(*) FROM auth_role_permissions rp JOIN auth_roles r ON r.id=rp.role_id JOIN auth_permissions p ON p.id=rp.permission_id WHERE r.role_key=".$pdo->quote($role)." AND p.permission_key IN ('api.settings.user_permission.list','api.settings.user_permission.detail','api.settings.user_permission.save')");
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
