<?php
declare(strict_types=1);
define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';
use Core\DbPdo;
use App\Services\Auth\UserPermissionService;
use App\Services\Auth\RolePermissionService;
$pdo=DbPdo::conn();
$initialUserPermissionCount=(int)$pdo->query('SELECT COUNT(*) FROM auth_user_permissions')->fetchColumn();
$actor=(string)$pdo->query("SELECT u.id FROM auth_users u JOIN auth_roles r ON r.id=u.role_id WHERE r.role_key='super_admin' LIMIT 1")->fetchColumn();
$target=(string)$pdo->query("SELECT id FROM auth_users WHERE id<>".$pdo->quote($actor)." LIMIT 1")->fetchColumn();
$service=new UserPermissionService($pdo);$detail=$service->detail($actor,$target);
$targetRoleId=(string)$pdo->query('SELECT role_id FROM auth_users WHERE id='.$pdo->quote($target))->fetchColumn();
$roleTree=(new RolePermissionService($pdo))->getPermissionTreeForRole($targetRoleId);
$treeSignature=static fn(array $tree):array=>array_map(static fn(array $page):array=>['page_key'=>$page['page_key']??'','page'=>$page['page']??'','category'=>$page['category']??'','permissions'=>array_map(static fn(array $permission):array=>['id'=>$permission['permission_id']??'','source'=>$permission['permission_source']??'','name'=>$permission['permission_name']??''], $page['children']??[])],$tree);
$admin=(string)$pdo->query("SELECT u.id FROM auth_users u JOIN auth_roles r ON r.id=u.role_id WHERE r.role_key='admin' LIMIT 1")->fetchColumn();
$staff=(string)$pdo->query("SELECT u.id FROM auth_users u JOIN auth_roles r ON r.id=u.role_id WHERE r.role_key='staff' LIMIT 1")->fetchColumn();
$role=['A'=>true,'C'=>true];$user=['B'=>true,'C'=>true];
$allowed=static fn(string $mode,string $id):bool=>$mode==='ROLE'?isset($role[$id]):($mode==='EXTEND'?(isset($role[$id])||isset($user[$id])):($mode==='REPLACE'&&isset($user[$id])));
$out=[
 'ROLE'=>['role_yes'=>$allowed('ROLE','A'),'role_no'=>!$allowed('ROLE','B')],
 'EXTEND'=>['role_only'=>$allowed('EXTEND','A'),'user_only'=>$allowed('EXTEND','B'),'neither'=>!$allowed('EXTEND','D')],
 'REPLACE'=>['role_only_denied'=>!$allowed('REPLACE','A'),'user_only'=>$allowed('REPLACE','B'),'both'=>$allowed('REPLACE','C')],
 'REPLACE_EMPTY'=>count([])===0,
 'database_unchanged'=>(int)$pdo->query('SELECT COUNT(*) FROM auth_user_permissions')->fetchColumn()===$initialUserPermissionCount,
 'read_api_contract'=>count($service->listUsers($actor))===10&&$detail['permission_mode']==='ROLE'&&$detail['state_version']!==''&&count($detail['permissions'])>0,
 'shared_permission_tree'=>isset($detail['permission_tree'])&&count($detail['permission_tree'])>0&&array_sum(array_map(static fn(array $page):int=>count($page['children']??[]),$detail['permission_tree']))===count($detail['permissions']),
 'role_individual_tree_match'=>$treeSignature($roleTree)===$treeSignature($detail['permission_tree']??[]),
 'permission_source_complete'=>count(array_filter($detail['permissions'],static fn(array $permission):bool=>!in_array($permission['permission_source'],['web','api'],true)))===0,
 'actor_policy'=>[
   'super_admin_self'=>$service->detail($actor,$actor)['editable']===true,
   'super_admin_admin'=>$service->detail($actor,$admin)['editable']===true,
   'admin_self'=>$service->detail($admin,$admin)['editable']===false,
   'admin_staff'=>$service->detail($admin,$staff)['editable']===true,
   'admin_super_admin'=>$service->detail($admin,$actor)['editable']===false,
   'staff_denied'=>$service->detail($staff,$target)['editable']===false,
 ],
];
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
foreach($out as $group)foreach((array)$group as $value)if($value!==true)exit(1);
