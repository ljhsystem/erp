<?php
namespace App\Services\Auth;

use App\Models\Auth\PermissionModel;
use App\Models\Auth\RolePermissionModel;
use App\Models\System\PageRegistryModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;

class RolePermissionService
{
    private readonly PDO $pdo;
    private RolePermissionModel $model;
    private PermissionModel $permissionModel;
    private PageRegistryModel $pageRegistryModel;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->model = new RolePermissionModel($pdo);
        $this->permissionModel = new PermissionModel($pdo);
        $this->pageRegistryModel = new PageRegistryModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-auth.RolePermissionService');
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
                $assignedMap[$permissionId] = true;
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
                    'permission_description' => $pageMeta['permission_description'],
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
            $pageNodes[$pageKey]['children'][] = [
                'type' => 'permission',
                'permission_id' => $permissionId,
                'permission_key' => $permissionKey,
                'permission_source' => str_starts_with($permissionKey, 'web.') ? 'web' : 'api',
                'page_key' => $pageKey,
                'page' => $pageMeta['page'],
                'category' => $pageMeta['category'],
                'permission_name' => trim((string) ($row['permission_name'] ?? '')),
                'permission_description' => trim((string) ($row['description'] ?? '')),
                'checked' => isset($assignedMap[$permissionId]),
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
            'sort_no' => SequenceHelper::next('auth_role_permissions', 'sort_no'),
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
            foreach ($changes as $index => $row) {
                $permissionId = trim((string) ($row['permission_id'] ?? ''));
                $sortNo = (int) ($row['sort_no'] ?? 0);

                if ($permissionId === '') {
                    throw new \InvalidArgumentException('권한 ID가 누락되었습니다.');
                }

                if ($sortNo <= 0) {
                    throw new \InvalidArgumentException('권한 순번이 올바르지 않습니다.');
                }

                if (!$this->permissionModel->updateSortNo($permissionId, $sortNo)) {
                    throw new \RuntimeException(sprintf('권한 순서 저장에 실패했습니다. (%d)', $index + 1));
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
            $pageLabel = trim((string) ($pageRow['page_label'] ?? '')) ?: '미분류 페이지';
            $breadcrumb = trim((string) ($pageRow['breadcrumb'] ?? '')) ?: '기타';

            return [
                'page_key' => $pageKey !== '' ? $pageKey : $permissionKey,
                'page' => $pageLabel,
                'category' => $breadcrumb,
                'permission_name' => $pageLabel,
                'permission_description' => $this->normalizePageDescription(
                    $pageLabel,
                    (string) ($pageRow['page_description'] ?? ''),
                    $breadcrumb
                ),
            ];
        }

        $fallback = $this->buildFallbackPageMeta($row);
        return [
            'page_key' => $pageKey !== '' ? $pageKey : $fallback['page_key'],
            'page' => $fallback['page'],
            'category' => $fallback['category'],
            'permission_name' => $fallback['page'],
            'permission_description' => $this->normalizePageDescription($fallback['page'], '', $fallback['category']),
        ];
    }

    private function normalizePageDescription(string $pageLabel, string $pageDescription, string $breadcrumb): string
    {
        $pageLabel = trim($pageLabel);
        $pageDescription = trim($pageDescription);
        $breadcrumb = trim($breadcrumb);

        if ($pageDescription === '') {
            return $pageLabel . ' 정보관리';
        }

        if ($breadcrumb !== '' && ($pageDescription === $breadcrumb || str_contains($pageDescription, $breadcrumb))) {
            return $pageLabel . ' 정보관리';
        }

        return $pageDescription;
    }

    private function buildFallbackPageMeta(array $row): array
    {
        $permissionKey = trim((string) ($row['permission_key'] ?? ''));
        $description = trim((string) ($row['description'] ?? ''));
        $category = trim((string) ($row['category'] ?? ''));
        $parts = array_values(array_filter(array_map('trim', explode('>', $description)), static fn(string $part): bool => $part !== ''));

        $page = '미분류 페이지';
        if (count($parts) >= 2) {
            $page = $parts[count($parts) - 2];
        } elseif (str_starts_with($permissionKey, 'web.') && count($parts) >= 1) {
            $page = $parts[count($parts) - 1];
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
