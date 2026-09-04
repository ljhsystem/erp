<?php
namespace App\Services\Auth;

use App\Models\Auth\PermissionModel;
use App\Models\Auth\RolePermissionModel;
use App\Models\System\PageRegistryModel;
use App\Repositories\Auth\UserPermissionRepository;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\Helpers\PermissionSourceHelper;
use Core\Helpers\PermissionPresentationHelper;
use Core\LoggerFactory;
use Core\PermissionRegistry;
use PDO;
use Psr\Log\LoggerInterface;

class RolePermissionService
{
    private const PROTECTED_ROLE_KEY = 'super_admin';
    private const REQUIRED_MANAGEMENT_PERMISSION_KEYS = [
        'web.settings.organization.permission-assignment',
        'api.settings.rolepermission.list',
        'api.settings.rolepermission.assign',
    ];
    private readonly PDO $pdo;
    private RolePermissionModel $model;
    private PermissionModel $permissionModel;
    private PageRegistryModel $pageRegistryModel;
    private UserPermissionRepository $userPermissionRepository;
    private LoggerInterface $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->model = new RolePermissionModel($pdo);
        $this->permissionModel = new PermissionModel($pdo);
        $this->pageRegistryModel = new PageRegistryModel($pdo);
        $this->userPermissionRepository = new UserPermissionRepository($pdo);
        $this->logger = LoggerFactory::getLogger('service-auth-role-permission');
    }

    public function getPermissionsForRole(string $roleId): array
    {
        return $this->model->getPermissionsForRole($roleId);
    }

    public function getPermissionTreeForRole(string $roleId = ''): array
    {
        $registeredPermissions = PermissionRegistry::all();
        $registeredKeys = $registeredPermissions !== [] ? array_fill_keys(array_keys($registeredPermissions), true) : [];
        $permissionRows = array_values(array_filter(
            $this->permissionModel->getAll(),
            static fn(array $row): bool => (int) ($row['is_active'] ?? 1) === 1
                && ($registeredKeys === [] || isset($registeredKeys[(string) ($row['permission_key'] ?? '')]))
        ));
        $assignedRows = $roleId === '' ? [] : $this->model->getPermissionsForRole($roleId);

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
            $presentation = PermissionPresentationHelper::decorate($row, $pageMeta['page']);
            $pageNodes[$pageKey]['children'][] = [
                'type' => 'permission',
                'id' => $permissionId,
                'permission_id' => $permissionId,
                'role_permission_id' => (string) ($assignedRow['mapping_id'] ?? ''),
                'role_id' => (string) ($assignedRow['mapping_role_id'] ?? $roleId),
                'role_permission_created_at' => $assignedRow['created_at'] ?? '',
                'role_permission_created_by' => $assignedRow['created_by'] ?? '',
                'permission_key' => $permissionKey,
                'permission_source' => PermissionSourceHelper::resolve($row),
                'page_key' => $pageKey,
                'page' => $pageMeta['page'],
                'category' => $pageMeta['category'],
                'permission_name' => $presentation['permission_name'],
                'description' => $presentation['description'],
                'capability_group' => $presentation['capability_group'],
                'risk_level' => $presentation['risk_level'],
                'metadata_status' => $presentation['metadata_status'],
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

    public function getPermissionSelectionForRole(string $roleId): array
    {
        $roleId = trim($roleId);
        if ($roleId === '') {
            throw new \InvalidArgumentException('역할 ID가 필요합니다.');
        }

        return [
            'role_id' => $roleId,
            'mappings' => $this->model->getPermissionSelectionForRole($roleId),
        ];
    }

    public function getRolesForPermission(string $permissionId): array
    {
        return $this->model->getRolesForPermission($permissionId);
    }

    public function saveRolePermissions(string $roleId, array $selectedPermissionIds): array
    {
        return $this->logged('ROLE_PERMISSION_SAVE', 'save', ['role_id' => $roleId, 'selected_count' => count($selectedPermissionIds)], fn(): array => $this->saveRolePermissionsInternal($roleId, $selectedPermissionIds));
    }

    private function saveRolePermissionsInternal(string $roleId, array $selectedPermissionIds): array
    {
        $roleId = trim($roleId);
        $selectedPermissionIds = array_values(array_unique(array_filter(array_map(
            static fn($id): string => trim((string) $id),
            $selectedPermissionIds
        ))));
        $role = $roleId === '' ? null : $this->model->getActiveRole($roleId);
        if ($role === null) {
            throw new \InvalidArgumentException('저장할 활성 역할을 확인해 주세요.');
        }
        $validPermissionIds = $this->model->activePermissionIds($selectedPermissionIds);
        if (count($validPermissionIds) !== count($selectedPermissionIds)) {
            throw new \InvalidArgumentException('저장할 권한 목록에 유효하지 않은 권한이 있습니다.');
        }

        $outer = $this->pdo->inTransaction();
        if (!$outer) {
            $this->pdo->beginTransaction();
        }
        try {
            $this->model->lockAdministratorPermissionScope($roleId);
            $requiredPermissionMap = $this->model->permissionIdsByKeys(self::REQUIRED_MANAGEMENT_PERMISSION_KEYS);
            if (count($requiredPermissionMap) !== count(self::REQUIRED_MANAGEMENT_PERMISSION_KEYS)) {
                throw new \RuntimeException('핵심 권한 정보를 확인할 수 없습니다.');
            }
            $requiredPermissionIds = array_values($requiredPermissionMap);
            if (($role['role_key'] ?? '') === self::PROTECTED_ROLE_KEY
                && array_diff($requiredPermissionIds, $validPermissionIds) !== []) {
                throw new \InvalidArgumentException('최고관리자의 핵심 관리 권한은 해제할 수 없습니다.');
            }
            if ($this->userPermissionRepository->countRecoveryAdministrators(
                $requiredPermissionIds,
                $roleId,
                $validPermissionIds
            ) < 1) {
                throw new \InvalidArgumentException('권한을 복구할 수 있는 활성 관리자가 최소 1명 이상 필요합니다.');
            }

            $assignedPermissionIds = $this->model->assignedPermissionIds($roleId);
            $toAdd = array_values(array_diff($validPermissionIds, $assignedPermissionIds));
            $toRemove = array_values(array_diff($assignedPermissionIds, $validPermissionIds));
            $removedCount = $this->model->removePermissions($roleId, $toRemove);
            $actor = ActorHelper::user();
            $addedCount = $this->model->insertMappings(array_map(
                static fn(string $permissionId): array => [
                    'id' => UuidHelper::generate(),
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_by' => $actor,
                ],
                $toAdd
            ));
            if (!$outer) {
                $this->pdo->commit();
            }
            return [
                'selected_count' => count($validPermissionIds),
                'added_count' => $addedCount,
                'removed_count' => $removedCount,
            ];
        } catch (\Throwable $exception) {
            if (!$outer && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function reorderPermissions(array $changes): void
    {
        $this->logged('ROLE_PERMISSION_REORDER', 'reorder', ['change_count' => count($changes)], function () use ($changes): bool { $this->reorderPermissionsInternal($changes); return true; });
    }

    private function reorderPermissionsInternal(array $changes): void
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

    private function logged(string $eventCode, string $action, array $context, callable $operation): mixed
    {
        try { $result = $operation(); $this->logger->warning('역할 권한 업무 처리를 완료했습니다.', ['event_code' => $eventCode, 'result' => 'SUCCESS', 'service' => self::class, 'action' => $action, 'actor' => ActorHelper::user()] + $context); return $result; }
        catch (\InvalidArgumentException|\DomainException $exception) { $this->logger->warning('역할 권한 업무 처리가 차단되었습니다.', ['event_code' => $eventCode . '_BLOCKED', 'result' => 'BLOCKED', 'service' => self::class, 'action' => $action, 'actor' => ActorHelper::user(), 'error_code' => get_class($exception), 'error' => $exception] + $context); throw $exception; }
        catch (\Throwable $exception) { $this->logger->error('역할 권한 업무 처리에 실패했습니다.', ['event_code' => $eventCode . '_FAILED', 'result' => 'FAILED', 'service' => self::class, 'action' => $action, 'actor' => ActorHelper::user(), 'error_code' => get_class($exception), 'error' => $exception] + $context); throw $exception; }
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
            $resolvedPageLabel = trim((string) ($pageRow['page_label'] ?? ''))
                ?: ($pageLabel !== '' ? $pageLabel : '미분류 페이지');
            $breadcrumb = trim((string) ($pageRow['breadcrumb'] ?? '')) ?: '기타';

            return [
                'page_key' => $pageKey !== '' ? $pageKey : $permissionKey,
                'page' => $resolvedPageLabel,
                'category' => $breadcrumb,
                'permission_name' => $resolvedPageLabel,
            ];
        }

        $inferredMeta = $this->inferPageMetaFromPermissionKey($permissionKey);
        if ($inferredMeta !== null) {
            return $inferredMeta;
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
            // PageRegistry에 아직 등록되지 않은 기존 페이지도 같은 업무 페이지끼리
            // 하나의 화면 그룹으로 유지한다. Permission Key별 MD5 그룹은 한 페이지를
            // 수십 개의 가짜 페이지로 분리하므로 사용하지 않는다.
            $pageKey = 'virtual.' . md5($fallbackCategory . '|' . $page);
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
                'web.settings.base-info.brand',
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

    private function inferPageMetaFromPermissionKey(string $permissionKey): ?array
    {
        $map = [
            'api.approval.leave-request.' => ['approval.leave_request', '휴가신청', '전자결재 > 휴가신청'],
            'api.approval.inbox.' => ['approval.inbox', '결재함', '전자결재 > 결재함'],
            'api.approval.personal-expense.' => ['approval.personal_expense', '개인경비 신청', '전자결재 > 개인경비'],
            'api.institution.human_resources.attendance.' => ['institution.human_resources.attendance', '근태관리', '대외기관업무 > 인사·노무관리'],
            'api.institution.human_resources.employment_contract.' => ['institution.human_resources.employment_contracts', '근로계약관리', '대외기관업무 > 인사·노무관리'],
            'api.institution.human_resources.employment_rules.' => ['web.institution.human_resources.employment_rules', '취업규칙·인사규정', '대외기관업무 > 인사·노무관리'],
            'api.institution.human_resources.job_assignment.' => ['institution.human_resources.job_assignments', '직무·배치관리', '대외기관업무 > 인사·노무관리'],
            'api.institution.human_resources.leave.' => ['web.institution.human_resources.leave', '휴가관리', '대외기관업무 > 인사·노무관리'],
            'api.institution.human_resources.pay_component.' => ['institution.human_resources.pay_components', '급여항목관리', '대외기관업무 > 인사·노무관리'],
            'api.institution.human_resources.personnel_action.' => ['institution.human_resources.personnel_actions', '인사발령관리', '대외기관업무 > 인사·노무관리'],
            'api.institution.human_resources.qualification_education.' => ['institution.human_resources.qualification_education', '자격·교육관리', '대외기관업무 > 인사·노무관리'],
            'api.institution.income_data.business_income.' => ['web.institution.income_data.business_income', '사업소득', '대외기관업무 > 소득자료관리'],
            'api.institution.income_data.daily_employment.' => ['institution.income_data.daily_employment', '일용근로소득', '대외기관업무 > 소득자료관리'],
            'api.institution.income_data.regular_employment.' => ['institution.income_data.regular_employment', '상용근로소득', '대외기관업무 > 소득자료관리'],
            'api.institution.social_insurance_eligibility.' => ['institution.social_insurance_eligibility', '사회보험 자격관리', '대외기관업무 > 4대보험업무'],
            'api.institution.social_insurance.' => ['web.institution.social_insurance', '사회보험 관리', '대외기관업무 > 4대보험업무'],
            'api.import.format.' => ['ledger.data.formats', '양식관리', '회계관리 > 자료관리'],
            'api.import.formats' => ['ledger.data.formats', '양식관리', '회계관리 > 자료관리'],
            'api.import.data_types' => ['ledger.data.formats', '양식관리', '회계관리 > 자료관리'],
            'api.import.' => ['ledger.data.upload', '자료업로드', '회계관리 > 자료관리'],
            'api.user.external_accounts.' => ['profile.view', '내정보 관리', '내정보 > 프로필'],
            'api.user.profile.' => ['profile.view', '내정보 관리', '내정보 > 프로필'],
            'api.ledger.evidence_metadata.' => ['ledger.data.evidence_metadata', '증빙정책', '회계관리 > 자료관리'],
            'api.settings.system.data_table_columns' => ['settings.system.table_settings', '테이블 설정 메타', '설정 > 시스템설정'],
            'api.settings.system.user-settings.' => ['settings.system.user_settings', '사용자 화면설정', '설정 > 시스템설정'],
            'web.user.profile.' => ['profile.view', '내정보 관리', '내정보 > 프로필'],
            'web.ledger.evidence_metadata' => ['ledger.data.evidence_metadata', '증빙정책', '회계관리 > 자료관리'],
            'web.ledger.data.bank-transactions' => ['ledger.data.list', '증빙원본', '회계관리 > 자료관리'],
            'web.ledger.data.tax-invoices' => ['ledger.data.list', '증빙원본', '회계관리 > 자료관리'],
            'web.ledger.funds.cash_ledger' => ['ledger.funds.cash_ledger', '현금출납장', '회계관리 > 자금관리'],
            'web.ledger.funds.deposit_ledger' => ['ledger.funds.deposit_ledger', '예금출납장', '회계관리 > 자금관리'],
            'web.ledger.funds.unlinked_transactions' => ['ledger.funds.unlinked_transactions', '미연결입출금', '회계관리 > 자금관리'],
            'web.institution.human_resources.attendance' => ['institution.human_resources.attendance', '근태관리', '대외기관업무 > 인사·노무관리'],
            'web.institution.human_resources.compensation_incentives' => ['institution.human_resources.compensation_incentives', '보상·인센티브관리', '대외기관업무 > 인사·노무관리'],
            'web.institution.human_resources.employment_contracts' => ['institution.human_resources.employment_contracts', '근로계약관리', '대외기관업무 > 인사·노무관리'],
            'web.institution.human_resources.job_assignments' => ['institution.human_resources.job_assignments', '직무·배치관리', '대외기관업무 > 인사·노무관리'],
            'web.institution.human_resources.performance_evaluations' => ['institution.human_resources.performance_evaluations', '성과평가관리', '대외기관업무 > 인사·노무관리'],
            'web.institution.human_resources.personnel_actions' => ['institution.human_resources.personnel_actions', '인사발령관리', '대외기관업무 > 인사·노무관리'],
            'web.institution.human_resources.qualification_education' => ['institution.human_resources.qualification_education', '자격·교육관리', '대외기관업무 > 인사·노무관리'],
            'web.institution.income_data.business_income' => ['web.institution.income_data.business_income', '사업소득', '대외기관업무 > 소득자료관리'],
            'web.institution.income_data.daily_employment' => ['institution.income_data.daily_employment', '일용근로소득', '대외기관업무 > 소득자료관리'],
            'web.institution.income_data.regular_employment' => ['institution.income_data.regular_employment', '상용근로소득', '대외기관업무 > 소득자료관리'],
            'web.institution.dashboard' => ['institution.dashboard', '대외기관업무', '대외기관업무'],
            'web.institution.filing_history' => ['institution.filing_history', '신고이력', '대외기관업무'],
            'web.institution.local_tax' => ['institution.local_tax', '지방세업무', '대외기관업무'],
            'web.institution.national_tax' => ['institution.national_tax', '국세업무', '대외기관업무'],
            'web.institution.tax_agent' => ['institution.tax_agent', '세무사업무', '대외기관업무'],
        ];

        foreach ($map as $prefix => [$pageKey, $page, $category]) {
            if ($permissionKey === $prefix || str_starts_with($permissionKey, $prefix)) {
                return [
                    'page_key' => $pageKey,
                    'page' => $page,
                    'category' => $category,
                    'permission_name' => $page,
                ];
            }
        }

        return null;
    }
}
