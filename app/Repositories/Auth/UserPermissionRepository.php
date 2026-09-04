<?php
namespace App\Repositories\Auth;

use Core\Helpers\PermissionSourceHelper;
use Core\Helpers\ActorHelper;
use PDO;

class UserPermissionRepository
{
    public function __construct(private readonly PDO $db) {}

    public function listUsers(): array
    {
        return $this->db->query("SELECT e.*,u.username,u.approved,u.is_active,u.role_id,
            r.role_key,r.role_name,r.is_active role_active,
            COALESCE(pr.permission_mode,'ROLE') permission_mode,COALESCE(pc.user_permission_count,0) user_permission_count
          FROM user_employees e JOIN auth_users u ON u.id=e.user_id LEFT JOIN auth_roles r ON r.id=u.role_id
          LEFT JOIN auth_user_permission_profiles pr ON pr.user_id=u.id
          LEFT JOIN (SELECT user_id,COUNT(*) user_permission_count FROM auth_user_permissions GROUP BY user_id) pc ON pc.user_id=u.id
          ORDER BY e.sort_no,e.employee_name,u.username")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function userContext(string $userId, bool $lock = false): ?array
    {
        $sql = "SELECT u.id user_id,u.username,u.approved,u.is_active,u.role_id,r.role_key,r.role_name,r.is_active role_active,
          e.employee_name,e.employment_status,e.doc_retire_date,e.real_retire_date,COALESCE(pr.permission_mode,'ROLE') permission_mode
          FROM auth_users u LEFT JOIN auth_roles r ON r.id=u.role_id LEFT JOIN user_employees e ON e.user_id=u.id
          LEFT JOIN auth_user_permission_profiles pr ON pr.user_id=u.id WHERE u.id=? LIMIT 1" . ($lock ? ' FOR UPDATE' : '');
        $statement=$this->db->prepare($sql); $statement->execute([$userId]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function permissionMaster(): array
    {
        $rows=$this->db->query('SELECT id,id permission_id,sort_no,page,permission_source,category,permission_key,permission_name,description,page_key,is_active,created_at,created_by,updated_at,updated_by FROM auth_permissions WHERE is_active=1 ORDER BY sort_no,permission_key')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach($rows as &$row)$row['permission_source']=PermissionSourceHelper::resolve($row);
        unset($row);
        return ActorHelper::enrichActorNames($rows,['created_by_name'=>'created_by','updated_by_name'=>'updated_by']);
    }

    public function permissionMap(array $ids = []): array
    {
        $sql='SELECT id permission_id,permission_key,permission_name FROM auth_permissions WHERE is_active=1'; $params=[];
        if ($ids !== []) { $sql .= ' AND id IN (' . implode(',',array_fill(0,count($ids),'?')) . ')'; $params=array_values($ids); }
        $s=$this->db->prepare($sql); $s->execute($params); $out=[];
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) $out[(string)$row['permission_id']]=$row;
        return $out;
    }

    public function permissionMapByKeys(array $keys): array
    {
        if ($keys===[]) return [];
        $s=$this->db->prepare('SELECT id permission_id,permission_key FROM auth_permissions WHERE is_active=1 AND permission_key IN ('.implode(',',array_fill(0,count($keys),'?')).')');
        $s->execute(array_values($keys)); $out=[]; foreach($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) $out[(string)$row['permission_id']]=$row['permission_key']; return $out;
    }

    public function rolePermissionIds(string $roleId): array
    { $s=$this->db->prepare('SELECT rp.permission_id FROM auth_role_permissions rp JOIN auth_permissions p ON p.id=rp.permission_id AND p.is_active=1 WHERE rp.role_id=?'); $s->execute([$roleId]); return array_map('strval',$s->fetchAll(PDO::FETCH_COLUMN) ?: []); }

    public function userPermissionIds(string $userId, bool $lock=false): array
    { $s=$this->db->prepare('SELECT permission_id FROM auth_user_permissions WHERE user_id=?'.($lock?' FOR UPDATE':'')); $s->execute([$userId]); return array_map('strval',$s->fetchAll(PDO::FETCH_COLUMN) ?: []); }

    public function clearPermissions(array $permissionIds): int
    {
        if ($permissionIds === []) return 0;
        $affected = 0;
        foreach (array_chunk(array_values(array_unique($permissionIds)), 200) as $chunk) {
            $statement = $this->db->prepare('DELETE FROM auth_user_permissions WHERE permission_id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')');
            $statement->execute($chunk);
            $affected += $statement->rowCount();
        }
        return $affected;
    }

    public function effectivePermissionSet(string $userId): array
    {
        $u=$this->userContext($userId); if(!$u || (int)$u['approved']!==1 || (int)$u['is_active']!==1 || !$u['role_id'] || (int)$u['role_active']!==1) return [];
        $mode=(string)$u['permission_mode'];
        $role=array_fill_keys($this->rolePermissionIds((string)$u['role_id']),true); $personal=array_fill_keys($this->userPermissionIds($userId),true); $out=[];
        foreach($this->permissionMaster() as $p){$id=(string)$p['permission_id']; $allowed=$mode==='ROLE'?isset($role[$id]):($mode==='EXTEND'?(isset($role[$id])||isset($personal[$id])):($mode==='REPLACE'&&isset($personal[$id]))); if($allowed)$out[$id]=(string)$p['permission_key'];}
        return $out;
    }

    public function countRecoveryAdministrators(array $requiredIds, ?string $changedRoleId=null, ?array $changedRoleIds=null, ?string $changedUserId=null, ?string $changedMode=null, ?array $changedUserIds=null): int
    {
        $users=$this->db->query("SELECT u.id user_id,u.role_id,r.role_key FROM auth_users u JOIN auth_roles r ON r.id=u.role_id WHERE u.approved=1 AND u.is_active=1 AND r.is_active=1 AND r.role_key IN ('super_admin','admin') FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC) ?: []; $count=0;
        foreach($users as $u){$role=(string)$u['role_id']===(string)$changedRoleId&&$changedRoleIds!==null?$changedRoleIds:$this->rolePermissionIds((string)$u['role_id']); $ctx=$this->userContext((string)$u['user_id']); $mode=(string)$u['user_id']===(string)$changedUserId&&$changedMode!==null?$changedMode:(string)($ctx['permission_mode']??'ROLE'); $personal=(string)$u['user_id']===(string)$changedUserId&&$changedUserIds!==null?$changedUserIds:$this->userPermissionIds((string)$u['user_id']); $effective=$mode==='ROLE'?$role:($mode==='EXTEND'?array_unique(array_merge($role,$personal)):$personal); if(array_diff($requiredIds,$effective)===[])$count++;}
        return $count;
    }
}
