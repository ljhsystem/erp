<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$registry = file_get_contents($root . '/core/PermissionRegistry.php');
$router = file_get_contents($root . '/core/Router.php');
$service = file_get_contents($root . '/app/Services/Auth/PermissionService.php');
$model = file_get_contents($root . '/app/Models/Auth/PermissionModel.php');
$roleModel = file_get_contents($root . '/app/Models/Auth/RolePermissionModel.php');
$userRepository = file_get_contents($root . '/app/Repositories/Auth/UserPermissionRepository.php');

if ($registry === false || $router === false || $service === false || $model === false || $roleModel === false || $userRepository === false) {
    fwrite(STDERR, "권한 현재상태 동기화 검사 파일을 읽지 못했습니다.\n");
    exit(1);
}

$contracts = [
    [str_contains($router, "(\$permission['permission_key'] ?? null) ?: \$permission['key']"), '실제 검사 Permission Key가 Registry에 등록되지 않습니다.'],
    [str_contains($registry, '!isset(self::$permissions[$permissionKey])'), '삭제된 Route Permission 판정이 없습니다.'],
    [str_contains($registry, 'deleteRegistryPermissions($staleIds'), '삭제된 Route Permission 물리삭제 호출이 없습니다.'],
    [str_contains($registry, 'PERMISSION_ROUTE_DELETION_SKIPPED'), '등록 실패 시 대량 물리삭제 방지 계약이 없습니다.'],
    [str_contains($registry, 'self::$conflicts === []'), '중복 Permission 메타 충돌 시 물리삭제 방지 계약이 없습니다.'],
    [str_contains($registry, "getenv('ERP_PERMISSION_ROUTE_HARD_DELETE_ENABLED') === '1'"), '운영 승인 전 Permission 물리삭제 잠금장치가 없습니다.'],
    [str_contains($model, 'DELETE FROM auth_permissions WHERE id IN'), 'Permission 원본 물리삭제 Query가 없습니다.'],
    [str_contains($service, '$this->rolePermModel->clearPermissions($ids)'), '역할 Permission Mapping 삭제가 없습니다.'],
    [str_contains($service, '$this->userPermissionRepository->clearPermissions($ids)'), '개인 Permission Mapping 삭제가 없습니다.'],
    [str_contains($roleModel, 'DELETE FROM auth_role_permissions WHERE permission_id IN'), '역할 Permission Mapping 물리삭제 Query가 없습니다.'],
    [str_contains($userRepository, 'DELETE FROM auth_user_permissions WHERE permission_id IN'), '개인 Permission Mapping 물리삭제 Query가 없습니다.'],
    [str_contains($service, '$this->pdo->beginTransaction()'), 'Permission 물리삭제 Service 트랜잭션이 없습니다.'],
    [str_contains($service, '$this->pdo->rollBack()'), 'Permission 물리삭제 실패 Rollback이 없습니다.'],
];

foreach ($contracts as [$passed, $message]) {
    if (!$passed) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

echo "Permission registry current-state sync contract PASS\n";
