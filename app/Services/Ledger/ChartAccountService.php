<?php

namespace App\Services\Ledger;

use App\Models\Ledger\ChartAccountModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ChartAccountService
{
    private ChartAccountModel $model;
    private SubAccountPolicyService $policyService;
    private CustomSubAccountService $customSubAccountService;
    private $logger;

    public function __construct(private readonly PDO $pdo)
    {
        $this->model = new ChartAccountModel($pdo);
        $this->policyService = new SubAccountPolicyService($pdo);
        $this->customSubAccountService = new CustomSubAccountService($pdo);
        $this->logger = LoggerFactory::getLogger('service-ledger.ChartAccountService');
        $this->logger->info('ChartAccountService initialized');
    }

    public function getAll(): array
    {
        try {
            return $this->model->getAll();
        } catch (\Throwable $e) {
            $this->logger->error('getAll failed', [
                'exception' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function getTree(): array
    {
        try {
            return $this->model->getTree();
        } catch (\Throwable $e) {
            $this->logger->error('getTree failed', [
                'exception' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function getById(string $id): ?array
    {
        try {
            return $this->model->getById($id);
        } catch (\Throwable $e) {
            $this->logger->error('getById failed', [
                'id' => $id,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function getByAccountCode(string $accountCode): ?array
    {
        try {
            return $this->model->getByAccountCode($accountCode);
        } catch (\Throwable $e) {
            $this->logger->error('getByAccountCode failed', [
                'account_code' => $accountCode,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function create(array $data): array
    {
        try {
            $actor = ActorHelper::user();

            if ($this->model->getByAccountCode($data['account_code'])) {
                return [
                    'success' => false,
                    'message' => '이미 존재하는 계정코드입니다.',
                ];
            }

            if (!empty($data['parent_id']) && !$this->model->getById($data['parent_id'])) {
                return [
                    'success' => false,
                    'message' => '상위 계정을 찾을 수 없습니다.',
                ];
            }

            $this->pdo->beginTransaction();

            $data['id'] = UuidHelper::generate();
            $data['level'] = $this->resolveLevel($data['parent_id'] ?? null);
            $data['allow_sub_account'] = 0;
            $data['created_by'] = $actor;
            $data['updated_by'] = $actor;

            if (!$this->model->create($data)) {
                $this->pdo->rollBack();

                return [
                    'success' => false,
                    'message' => '계정 생성에 실패했습니다.',
                ];
            }

            if (array_key_exists('sub_policies', $data)) {
                $policyResult = $this->policyService->replacePolicies(
                    $data['id'],
                    $data['sub_policies'] ?? [],
                    $data['updated_by'] ?? $data['created_by'] ?? null
                );

                if (!$policyResult['success']) {
                    $this->pdo->rollBack();
                    return $policyResult;
                }
            }

            $this->syncLegacyAllowSubAccountFlag($data['id']);
            $this->pdo->commit();

            return [
                'success' => true,
                'id' => $data['id'],
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('create failed', [
                'data' => $data,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => '계정 생성 중 오류가 발생했습니다. ' . $e->getMessage(),
            ];
        }
    }

    public function update(string $id, array $data): array
    {
        try {
            $data['updated_by'] = ActorHelper::user();
            $existing = $this->model->getById($id);
            if (!$existing) {
                return [
                    'success' => false,
                    'message' => '계정??李얠쓣 ???놁뒿?덈떎.',
                ];
            }

            $data = array_merge($existing, $data);

            $exists = $this->model->getByAccountCode($data['account_code']);
            if ($exists && $exists['id'] !== $id) {
                return [
                    'success' => false,
                    'message' => '?? 존재?는 계정코드?니??',
                ];
            }

            if (!empty($data['parent_id']) && $data['parent_id'] === $id) {
                return [
                    'success' => false,
                    'message' => '?기 ?신???위 계정?로 吏?뺥븷 ???놁뒿?덈떎.',
                ];
            }

            if (!empty($data['parent_id']) && !$this->model->getById($data['parent_id'])) {
                return [
                    'success' => false,
                    'message' => '?위 계정??李얠쓣 ???놁뒿?덈떎.',
                ];
            }

            $this->pdo->beginTransaction();

            $data['level'] = $this->resolveLevel($data['parent_id'] ?? null);

            if (!$this->model->update($id, $data)) {
                $this->pdo->rollBack();

                return [
                    'success' => false,
                    'message' => '계정 ?정???패?습?다.',
                ];
            }

            if (array_key_exists('sub_policies', $data)) {
                $policyResult = $this->policyService->replacePolicies(
                    $id,
                    $data['sub_policies'] ?? [],
                    $data['updated_by'] ?? null
                );

                if (!$policyResult['success']) {
                    $this->pdo->rollBack();
                    return $policyResult;
                }
            }

            $this->syncLegacyAllowSubAccountFlag($id);
            $this->pdo->commit();

            return ['success' => true];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('update failed', [
                'id' => $id,
                'data' => $data,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => '계정 ?정 ??류 발생?습?다.',
            ];
        }
    }

    public function softDelete(string $id): array
    {
        $actor = ActorHelper::user();
        if ($this->model->hasChildren($id)) {
            return [
                'success' => false,
                'message' => '?위 계정??존재?여 ???????습?다.',
            ];
        }

        return ['success' => $this->model->softDelete($id, $actor)];
    }

    public function restore(string $id): array
    {
        $actor = ActorHelper::user();
        return ['success' => $this->model->restore($id, $actor)];
    }

    public function getTrashList(): array
    {
        return $this->model->getTrashList();
    }

    public function hardDelete(string $id): array
    {
        $actor = ActorHelper::user();
        if ($this->model->hasChildren($id)) {
            return [
                'success' => false,
                'message' => '?위 계정??존재?여 ?전 ???????습?다.',
            ];
        }

        return ['success' => $this->model->hardDelete($id, $actor)];
    }

    public function hasChildren(string $id): bool
    {
        return $this->model->hasChildren($id);
    }

    public function getTreeStructured(): array
    {
        $rows = $this->model->getTree();
        $map = [];
        $tree = [];

        foreach ($rows as &$row) {
            $row['children'] = [];
            $map[$row['id']] = &$row;
        }

        foreach ($rows as &$row) {
            if (!empty($row['parent_id']) && isset($map[$row['parent_id']])) {
                $map[$row['parent_id']]['children'][] = &$row;
            } else {
                $tree[] = &$row;
            }
        }

        return $tree;
    }

    public function findByCode(string $code): ?array
    {
        return $this->model->findByCode($code);
    }

    public function createSubAccount(array $data): array
    {
        return $this->customSubAccountService->create($data);
    }

    public function saveFromExcelFile(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, false, false, false);

            if (empty($rows) || count($rows) < 2) {
                return [
                    'success' => false,
                    'message' => '업로드할 데이터가 없습니다.',
                ];
            }

            $headerAliases = [
                '계정명' => 'account_name',
                'account_name' => 'account_name',
                '계정코드' => 'account_code',
                'account_code' => 'account_code',
                '상위계정' => 'parent_code',
                '상위계정코드' => 'parent_code',
                'parent_code' => 'parent_code',
                '사용여부' => 'is_active',
                'is_active' => 'is_active',
                '비고' => 'note',
                'note' => 'note',
                '구분' => 'account_group',
                '계정구분' => 'account_group',
                'account_group' => 'account_group',
                '보조계정' => 'sub_name',
                'sub_name' => 'sub_name',
            ];

            $headerAliases = array_merge($headerAliases, [
                '계정코드' => 'account_code',
                '코드' => 'account_code',
                '계정과목명' => 'account_name',
                '계정과목' => 'account_name',
                '계정명' => 'account_name',
                '상위계정코드' => 'parent_code',
                '상위계정' => 'parent_code',
                '계정구분' => 'account_group',
                '구분' => 'account_group',
                '정상잔액' => 'normal_balance',
                '차대구분' => 'normal_balance',
                '차/대' => 'normal_balance',
                'normal_balance' => 'normal_balance',
                '전표입력' => 'is_posting',
                '전표입력가능' => 'is_posting',
                'is_posting' => 'is_posting',
                '사용여부' => 'is_active',
                '비고' => 'note',
                '메모' => 'memo',
                'memo' => 'memo',
                '보조계정명' => 'sub_name',
                '보조계정' => 'sub_name',
            ]);

            $excelHeaders = array_map(
                static fn ($value) => trim((string) $value),
                $rows[0]
            );

            $columnMap = [];
            foreach ($excelHeaders as $index => $headerName) {
                if (isset($headerAliases[$headerName])) {
                    $columnMap[$headerAliases[$headerName]] = $index;
                }
            }

            if (!isset($columnMap['account_name']) || !isset($columnMap['account_code'])) {
                return [
                    'success' => false,
                    'message' => '엑셀 양식이 올바르지 않습니다. [계정코드, 계정과목명] 컬럼이 필요합니다.',
                ];
            }

            array_shift($rows);

            $createdCount = 0;
            $updatedCount = 0;
            $errors = [];

            foreach ($rows as $rowIndex => $row) {
                if (count(array_filter($row, static fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                $accountCode = trim((string) ($row[$columnMap['account_code']] ?? ''));
                $accountName = trim((string) ($row[$columnMap['account_name']] ?? ''));
                $parentCode = trim((string) ($row[$columnMap['parent_code']] ?? ''));
                $rawIsActive = trim((string) ($row[$columnMap['is_active']] ?? ''));
                $note = trim((string) ($row[$columnMap['note']] ?? ''));
                $accountGroup = trim((string) ($row[$columnMap['account_group']] ?? ''));
                $subName = trim((string) ($row[$columnMap['sub_name']] ?? ''));
                $rawNormalBalance = trim((string) ($row[$columnMap['normal_balance']] ?? ''));
                $rawIsPosting = trim((string) ($row[$columnMap['is_posting']] ?? ''));
                $memo = trim((string) ($row[$columnMap['memo']] ?? ''));

                if ($accountCode === '' || $accountName === '') {
                    $errors[] = ($rowIndex + 2) . '행: 계정코드 또는 계정명이 비어 있습니다.';
                    continue;
                }

                $existing = $this->findByCode($accountCode);
                $parent = null;
                $parentColumnExists = isset($columnMap['parent_code']);
                $parentId = ($existing && !$parentColumnExists) ? ($existing['parent_id'] ?? null) : null;

                if (!isset($columnMap['note'])) {
                    $note = (string) ($existing['note'] ?? '');
                }

                if (!isset($columnMap['memo'])) {
                    $memo = (string) ($existing['memo'] ?? '');
                }

                if ($parentCode !== '') {
                    $parent = $this->findByCode($parentCode);
                    if (!$parent) {
                        $errors[] = ($rowIndex + 2) . "행: 상위계정 [{$parentCode}]를 찾을 수 없습니다.";
                        continue;
                    }

                    $parentId = $parent['id'] ?? null;
                }

                if ($accountGroup === '') {
                    if ($existing && !empty($existing['account_group'])) {
                        $accountGroup = (string) $existing['account_group'];
                    } elseif ($parent && !empty($parent['account_group'])) {
                        $accountGroup = (string) $parent['account_group'];
                    }
                }

                if ($accountGroup === '') {
                    $errors[] = ($rowIndex + 2) . "행: 신규 계정 [{$accountCode}]의 구분 값을 확인해주세요.";
                    continue;
                }

                $isActive = $this->normalizeExcelBoolean($rawIsActive, (int) ($existing['is_active'] ?? 1));
                $normalBalance = in_array($accountGroup, ['자산', '비용'], true) ? 'debit' : 'credit';

                $isPosting = $this->normalizeExcelBoolean($rawIsPosting, (int) ($existing['is_posting'] ?? 1));
                $normalBalance = $this->normalizeExcelNormalBalanceValue($rawNormalBalance);

                if ($rawNormalBalance !== '' && $normalBalance === null) {
                    $errors[] = ($rowIndex + 2) . "행 정상잔액 값이 올바르지 않습니다. [{$rawNormalBalance}]";
                    continue;
                }

                if ($normalBalance === null) {
                    $normalBalance = $this->inferNormalBalanceFromGroupValue(
                        $accountGroup,
                        (string) ($existing['normal_balance'] ?? 'debit')
                    );
                }

                $payload = [
                    'account_code' => $accountCode,
                    'account_name' => $accountName,
                    'parent_id' => $parentId,
                    'account_group' => $accountGroup,
                    'normal_balance' => $normalBalance,
                    'is_posting' => $isPosting,
                    'is_active' => $isActive,
                    'note' => $note,
                    'memo' => $memo,
                ];

                if ($existing) {
                    $result = $this->update($existing['id'], $payload);
                    if (!$result['success']) {
                        $errors[] = ($rowIndex + 2) . "행: {$accountCode} 수정 실패 - " . ($result['message'] ?? '원인을 확인할 수 없습니다.');
                        continue;
                    }

                    $updatedCount++;
                    $accountId = $existing['id'];
                } else {
                    $result = $this->create($payload);
                    if (!$result['success']) {
                        $errors[] = ($rowIndex + 2) . "행: {$accountCode} 생성 실패 - " . ($result['message'] ?? '원인을 확인할 수 없습니다.');
                        continue;
                    }

                    $createdCount++;
                    $accountId = $result['id'] ?? null;
                }

                if ($subName !== '' && $accountId) {
                    $subResult = $this->createSubAccount([
                        'account_id' => $accountId,
                        'sub_name' => $subName,
                    ]);

                    if (!($subResult['success'] ?? false)) {
                        $errors[] = ($rowIndex + 2) . "행: {$accountCode} 보조계정 생성 실패 - " . ($subResult['message'] ?? '원인을 확인할 수 없습니다.');
                    }
                }
            }

            $processedCount = $createdCount + $updatedCount;
            $errorCount = count($errors);

            if ($processedCount === 0 && $errorCount > 0) {
                return [
                    'success' => false,
                    'message' => '엑셀 업로드에 실패했습니다. ' . $errors[0],
                    'created_count' => $createdCount,
                    'updated_count' => $updatedCount,
                    'errors' => $errors,
                ];
            }

            $message = "엑셀 업로드 완료 (생성 {$createdCount}건, 수정 {$updatedCount}건";
            if ($errorCount > 0) {
                $message .= ", 실패 {$errorCount}건";
            }
            $message .= ')';

            return [
                'success' => true,
                'message' => $message,
                'created_count' => $createdCount,
                'updated_count' => $updatedCount,
                'errors' => $errors,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('saveFromExcelFile failed', [
                'file' => $filePath,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => '엑셀 업로드 중 오류가 발생했습니다. ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }
    public function getList(array $filters = []): array
    {
        $modelFilters = [];
        $statusFilters = [];

        foreach ($filters as $filter) {
            $field = $filter['field'] ?? '';
            if ($field === 'sub_account_status') {
                $statusFilters[] = $filter;
                continue;
            }

            $modelFilters[] = $filter;
        }

        $rows = $this->model->getList($modelFilters);

        foreach ($rows as &$row) {
            $accountId = (string) ($row['id'] ?? '');
            $allowSubAccount = (int) ($row['allow_sub_account'] ?? 0);
            $hasSubAccounts = $accountId !== '' && $this->customSubAccountService->countByAccountId($accountId) > 0;

            $row['sub_account_status'] = $hasSubAccounts
                ? '사용중'
                : ($allowSubAccount === 1 ? '가능' : '미사용');
        }
        unset($row);

        if (!empty($statusFilters)) {
            $rows = array_values(array_filter($rows, static function (array $row) use ($statusFilters): bool {
                $status = (string) ($row['sub_account_status'] ?? '');

                foreach ($statusFilters as $filter) {
                    $value = trim((string) ($filter['value'] ?? ''));
                    if ($value === '') {
                        continue;
                    }

                    if (mb_stripos($status, $value, 0, 'UTF-8') === false) {
                        return false;
                    }
                }

                return true;
            }));
        }

        $this->logger->info('getList returned', [
            'count' => count($rows),
        ]);

        return $rows;
    }

    public function reorder(array $changes): void
    {
        foreach ($changes as $row) {
            $this->model->updateOrder(
                $row['id'],
                (int) $row['newSortNo']
            );
        }
    }

    public function getDetailByAccountCode(string $accountCode): ?array
    {
        $row = $this->model->getDetailByAccountCode($accountCode);

        if (!$row) {
            return null;
        }

        $row['sub_policies'] = $this->policyService->getByAccountId($row['id']);
        $row['allow_sub_account_computed'] = (
            count($row['sub_policies']) > 0
            || $this->customSubAccountService->countByAccountId($row['id']) > 0
        ) ? 1 : 0;

        return $row;
    }

    public function restoreBulk(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $in = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $this->pdo->prepare("
            UPDATE ledger_accounts
            SET deleted_at = NULL,
                deleted_by = NULL
            WHERE id IN ($in)
        ");

        $stmt->execute($ids);
    }

    public function restoreAll(): void
    {
        $this->pdo->exec("
            UPDATE ledger_accounts
            SET deleted_at = NULL,
                deleted_by = NULL
            WHERE deleted_at IS NOT NULL
        ");
    }

    public function hardDeleteAll(): void
    {
        $this->pdo->exec("DELETE FROM ledger_accounts WHERE deleted_at IS NOT NULL");
    }

    private function resolveLevel(?string $parentId): int
    {
        if (!$parentId) {
            return 1;
        }

        $parent = $this->model->getById($parentId);

        return $parent ? ((int) $parent['level'] + 1) : 1;
    }

    private function syncLegacyAllowSubAccountFlag(string $accountId): void
    {
        $hasPolicies = $this->policyService->countByAccountId($accountId) > 0;
        $hasCustom = $this->customSubAccountService->countByAccountId($accountId) > 0;

        $this->model->updateAllowSubAccount(
            $accountId,
            ($hasPolicies || $hasCustom) ? 1 : 0
        );
    }

    private function normalizeExcelNormalBalance(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $normalized = mb_strtolower(trim($value), 'UTF-8');

        if (in_array($normalized, ['1', 'y', 'yes', 'true', '사용', '사용함', '가능', '허용', '예'], true)) {
            return 1;
        }

        if (in_array($normalized, ['0', 'n', 'no', 'false', '미사용', '사용안함', '불가', '미허용', '아니오'], true)) {
            return 0;
        }

        if (in_array($normalized, ['debit', '차변', '차', 'dr', 'd', '李⑤?'], true)) {
            return 'debit';
        }

        if (in_array($normalized, ['credit', '대변', '대', 'cr', 'c', '?蹂'], true)) {
            return 'credit';
        }

        return null;
    }

    private function inferNormalBalanceFromGroup(string $accountGroup, string $default = 'debit'): string
    {
        $normalized = trim($accountGroup);

        if (in_array($normalized, ['ڻ', '', '?산', '비용'], true)) {
            return 'debit';
        }

        if (in_array($normalized, ['부채', '자본', '수익', '遺梨?', '?먮낯', '?섏씡'], true)) {
            return 'credit';
        }

        return in_array($default, ['debit', 'credit'], true) ? $default : 'debit';
    }

    private function normalizeExcelNormalBalanceValue(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $normalized = mb_strtolower(trim($value), 'UTF-8');

        if (in_array($normalized, ['debit', '차변', '차', 'dr', 'd'], true)) {
            return 'debit';
        }

        if (in_array($normalized, ['credit', '대변', '대', 'cr', 'c'], true)) {
            return 'credit';
        }

        return null;
    }

    private function inferNormalBalanceFromGroupValue(string $accountGroup, string $default = 'debit'): string
    {
        $normalized = trim($accountGroup);

        if (in_array($normalized, ['자산', '비용'], true)) {
            return 'debit';
        }

        if (in_array($normalized, ['부채', '자본', '수익'], true)) {
            return 'credit';
        }

        return in_array($default, ['debit', 'credit'], true) ? $default : 'debit';
    }

    private function normalizeExcelBoolean(string $value, int $default): int
    {
        $quickNormalized = mb_strtolower(trim($value), 'UTF-8');

        if ($quickNormalized !== '' && in_array($quickNormalized, ['1', 'y', 'yes', 'true', '사용', '사용함', '가능', '허용', '예'], true)) {
            return 1;
        }

        if ($quickNormalized !== '' && in_array($quickNormalized, ['0', 'n', 'no', 'false', '미사용', '사용안함', '불가', '미허용', '아니오'], true)) {
            return 0;
        }
        if ($value === '') {
            return $default;
        }

        $normalized = mb_strtolower(trim($value), 'UTF-8');

        if (in_array($normalized, ['1', 'y', 'yes', 'true', '사용', '사용함'], true)) {
            return 1;
        }

        if (in_array($normalized, ['0', 'n', 'no', 'false', '미사용', '사용안함'], true)) {
            return 0;
        }

        return $default;
    }
}
