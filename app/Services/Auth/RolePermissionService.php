<?php
namespace App\Services\Auth;

use App\Models\Auth\PermissionModel;
use App\Models\Auth\RolePermissionModel;
use App\Models\System\PageRegistryModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PDO;

class RolePermissionService
{
    private readonly PDO $pdo;
    private RolePermissionModel $model;
    private PermissionModel $permissionModel;
    private PageRegistryModel $pageRegistryModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->model = new RolePermissionModel($pdo);
        $this->permissionModel = new PermissionModel($pdo);
        $this->pageRegistryModel = new PageRegistryModel($pdo);
    }

    public function getPermissionsForRole(string $roleId): array
    {
        return $this->model->getPermissionsForRole($roleId);
    }

    public function getPermissionTreeForRole(string $roleId): array
    {
        $permissionRows = array_values(array_filter(
            $this->permissionModel->getAll(),
            static fn(array $row): bool => (int) ($row['is_active'] ?? 1) === 1
        ));
        $assignedRows = $this->model->getPermissionsForRole($roleId);

        $assignedMap = [];
        foreach ($assignedRows as $row) {
            $permissionId = (string) ($row['permission_id'] ?? $row['id'] ?? '');
            if ($permissionId !== '') {
                $assignedMap[$permissionId] = $row;
            }
        }

        $pageRegistryByKey = [];
        $pageRegistryByRouteKey = [];
        foreach ($this->pageRegistryModel->getAll() as $row) {
            $pageKey = trim((string) ($row['page_key'] ?? ''));
            if ($pageKey !== '') {
                $pageRegistryByKey[$pageKey] = $row;
            }

            $routeKey = trim((string) ($row['default_route_key'] ?? ''));
            if ($routeKey !== '') {
                $pageRegistryByRouteKey[$routeKey] = $row;
            }
        }

        $pageNodes = [];
        foreach ($permissionRows as $row) {
            $pageMeta = $this->resolvePageMeta($row, $pageRegistryByKey, $pageRegistryByRouteKey);
            $pageKey = $pageMeta['page_key'];

            if (!isset($pageNodes[$pageKey])) {
                $pageNodes[$pageKey] = [
                    'type' => 'page',
                    'page_key' => $pageKey,
                    'page' => $pageMeta['page'],
                    'category' => $pageMeta['category'],
                    'permission_name' => $pageMeta['permission_name'],
                    'description' => '',
                    'checked' => false,
                    'indeterminate' => false,
                    'sort_no' => (int) ($row['sort_no'] ?? 0),
                    'children' => [],
                ];
            } else {
                $pageNodes[$pageKey]['sort_no'] = min(
                    $pageNodes[$pageKey]['sort_no'],
                    (int) ($row['sort_no'] ?? $pageNodes[$pageKey]['sort_no'])
                );
            }

            $permissionId = (string) ($row['id'] ?? '');
            $permissionKey = (string) ($row['permission_key'] ?? '');
            $assignedRow = $assignedMap[$permissionId] ?? null;
            $pageNodes[$pageKey]['children'][] = [
                'type' => 'permission',
                'id' => $permissionId,
                'permission_id' => $permissionId,
                'role_permission_id' => (string) ($assignedRow['mapping_id'] ?? ''),
                'role_id' => (string) ($assignedRow['mapping_role_id'] ?? $roleId),
                'role_permission_created_at' => $assignedRow['created_at'] ?? '',
                'role_permission_created_by' => $assignedRow['created_by'] ?? '',
                'permission_key' => $permissionKey,
                'permission_source' => $this->resolvePermissionSource($row),
                'page_key' => $pageKey,
                'page' => $pageMeta['page'],
                'category' => $pageMeta['category'],
                'permission_name' => trim((string) ($row['permission_name'] ?? '')),
                'description' => trim((string) ($row['description'] ?? '')),
                'is_active' => $row['is_active'] ?? '',
                'created_at' => $row['created_at'] ?? '',
                'created_by' => $row['created_by'] ?? '',
                'updated_at' => $row['updated_at'] ?? '',
                'updated_by' => $row['updated_by'] ?? '',
                'checked' => $assignedRow !== null,
                'sort_no' => (int) ($row['sort_no'] ?? 0),
            ];
        }

        $tree = array_values($pageNodes);
        usort($tree, static function (array $left, array $right): int {
            $sortCompare = ((int) ($left['sort_no'] ?? 0)) <=> ((int) ($right['sort_no'] ?? 0));
            if ($sortCompare !== 0) {
                return $sortCompare;
            }

            $categoryCompare = strcmp((string) ($left['category'] ?? ''), (string) ($right['category'] ?? ''));
            if ($categoryCompare !== 0) {
                return $categoryCompare;
            }

            return strcmp((string) ($left['page'] ?? ''), (string) ($right['page'] ?? ''));
        });

        foreach ($tree as $index => &$pageNode) {
            usort($pageNode['children'], static function (array $left, array $right): int {
                $sortCompare = ((int) ($left['sort_no'] ?? 0)) <=> ((int) ($right['sort_no'] ?? 0));
                if ($sortCompare !== 0) {
                    return $sortCompare;
                }

                return strcmp((string) ($left['permission_name'] ?? ''), (string) ($right['permission_name'] ?? ''));
            });

            $childCount = count($pageNode['children']);
            $checkedCount = count(array_filter(
                $pageNode['children'],
                static fn(array $child): bool => !empty($child['checked'])
            ));

            $pageNode['checked'] = $childCount > 0 && $checkedCount === $childCount;
            $pageNode['indeterminate'] = $checkedCount > 0 && $checkedCount < $childCount;
            $pageNode['sort_no'] = $index + 1;
        }
        unset($pageNode);

        return $tree;
    }

    public function getRolesForPermission(string $permissionId): array
    {
        return $this->model->getRolesForPermission($permissionId);
    }

    public function assign(string $roleId, string $permissionId): bool
    {
        if ($this->model->exists($roleId, $permissionId)) {
            return true;
        }

        $data = [
            'id' => UuidHelper::generate(),
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'created_by' => ActorHelper::user(),
        ];

        return $this->model->insertMapping($data);
    }

    public function remove(string $roleId, string $permissionId): bool
    {
        return $this->model->remove($roleId, $permissionId);
    }

    public function reorderPermissions(array $changes): void
    {
        if ($changes === []) {
            throw new \InvalidArgumentException('변경할 권한 순서가 없습니다.');
        }

        $this->pdo->beginTransaction();

        try {
            foreach ($changes as &$row) {
                $permissionId = trim((string) ($row['permission_id'] ?? ''));
                $sortNo = (int) ($row['sort_no'] ?? 0);

                if ($permissionId === '') {
                    throw new \InvalidArgumentException('권한 ID가 올바르지 않습니다.');
                }

                if ($sortNo <= 0) {
                    throw new \InvalidArgumentException('권한 순번이 올바르지 않습니다.');
                }

                $row['_sort_no'] = $sortNo;
            }
            unset($row);

            foreach ($changes as $index => $row) {
                if (!$this->permissionModel->updateSortNo($row['permission_id'], $row['_sort_no'] + 1000000)) {
                    throw new \RuntimeException(sprintf('정렬 저장 중 오류가 발생했습니다. (%d)', $index + 1));
                }
            }

            foreach ($changes as $index => $row) {
                if (!$this->permissionModel->updateSortNo($row['permission_id'], $row['_sort_no'])) {
                    throw new \RuntimeException(sprintf('정렬 저장 중 오류가 발생했습니다. (%d)', $index + 1));
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function clearRole(string $roleId): bool
    {
        return $this->model->clearRole($roleId);
    }

    public function clearPermission(string $permissionId): bool
    {
        return $this->model->clearPermission($permissionId);
    }

    public function roleHasPermission(string $roleId, string $permissionKey): bool
    {
        return $this->model->roleHasPermission($roleId, $permissionKey);
    }

    private function resolvePageMeta(array $row, array $pageRegistryByKey, array $pageRegistryByRouteKey): array
    {
        $pageKey = trim((string) ($row['page_key'] ?? ''));
        $permissionKey = trim((string) ($row['permission_key'] ?? ''));
        $pageLabel = trim((string) ($row['page'] ?? ''));
        $pageRow = null;

        if ($pageKey !== '' && isset($pageRegistryByKey[$pageKey])) {
            $pageRow = $pageRegistryByKey[$pageKey];
        } elseif ($permissionKey !== '' && isset($pageRegistryByRouteKey[$permissionKey])) {
            $pageRow = $pageRegistryByRouteKey[$permissionKey];
            $pageKey = trim((string) ($pageRow['page_key'] ?? ''));
        } else {
            $inferredPageKey = $this->inferPageKeyFromPermissionKey($permissionKey);
            if ($inferredPageKey !== '' && isset($pageRegistryByKey[$inferredPageKey])) {
                $pageRow = $pageRegistryByKey[$inferredPageKey];
                $pageKey = $inferredPageKey;
            }
        }

        if ($pageRow) {
            $resolvedPageLabel = $pageLabel !== ''
                ? $pageLabel
                : (trim((string) ($pageRow['page_label'] ?? '')) ?: '미분류 페이지');
            $breadcrumb = trim((string) ($pageRow['breadcrumb'] ?? '')) ?: '기타';

            return [
                'page_key' => $pageKey !== '' ? $pageKey : $permissionKey,
                'page' => $resolvedPageLabel,
                'category' => $breadcrumb,
                'permission_name' => $resolvedPageLabel,
            ];
        }

        $fallback = $this->buildFallbackPageMeta($row);
        return [
            'page_key' => $pageKey !== '' ? $pageKey : $fallback['page_key'],
            'page' => $pageLabel !== '' ? $pageLabel : $fallback['page'],
            'category' => $fallback['category'],
            'permission_name' => $pageLabel !== '' ? $pageLabel : $fallback['page'],
        ];
    }

    private function buildFallbackPageMeta(array $row): array
    {
        $permissionKey = trim((string) ($row['permission_key'] ?? ''));
        $description = trim((string) ($row['description'] ?? ''));
        $category = trim((string) ($row['category'] ?? ''));
        $pageLabel = trim((string) ($row['page'] ?? ''));
        $parts = array_values(array_filter(array_map('trim', explode('>', $description)), static fn(string $part): bool => $part !== ''));

        $page = $pageLabel !== '' ? $pageLabel : '미분류 페이지';
        if ($pageLabel === '') {
            if (count($parts) >= 2) {
                $page = $parts[count($parts) - 2];
            } elseif (str_starts_with($permissionKey, 'web.') && count($parts) >= 1) {
                $page = $parts[count($parts) - 1];
            }
        }

        $fallbackCategory = $category !== '' ? $category : '기타';
        if (count($parts) >= 2) {
            $fallbackCategory = implode(' > ', array_slice($parts, 0, -1));
        }

        $pageKey = $this->inferPageKeyFromPermissionKey($permissionKey);
        if ($pageKey === '') {
            $pageKey = 'unmapped.' . md5($permissionKey !== '' ? $permissionKey : ($page . '.' . $fallbackCategory));
        }

        return [
            'page_key' => $pageKey,
            'page' => $page,
            'category' => $fallbackCategory,
        ];
    }

    private function resolvePermissionSource(array $row): string
    {
        $source = strtolower(trim((string) ($row['permission_source'] ?? '')));
        if ($source === 'web' || $source === 'api') {
            return $source;
        }

        $permissionKey = trim((string) ($row['permission_key'] ?? ''));
        return str_starts_with($permissionKey, 'web.') ? 'web' : 'api';
    }

    private function inferPageKeyFromPermissionKey(string $permissionKey): string
    {
        $map = [
            'ledger.data.formats' => [
                'api.import.formats',
            ],
            'ledger.data.upload' => [
                'api.import.seed_rows',
            ],
            'settings.base_info.brand' => [
                'api.settings.base-info.brand.',
                'web.settings.base-info.brand_logo',
            ],
            'settings.base_info.cover' => [
                'api.settings.base-info.cover.',
                'web.settings.base-info.cover',
            ],
        ];

        foreach ($map as $pageKey => $prefixes) {
            foreach ($prefixes as $prefix) {
                if ($permissionKey === $prefix || str_starts_with($permissionKey, $prefix)) {
                    return $pageKey;
                }
            }
        }

        return '';
    }
}
