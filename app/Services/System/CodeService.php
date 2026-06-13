<?php
namespace App\Services\System;

use App\Models\System\CodeModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PDO;

class CodeService
{
    private readonly PDO $pdo;
    private CodeModel $model;
    private $logger;

    private const REFERENCE_MAP = [
        'BANK' => [
            ['table' => 'system_bank_accounts', 'column' => 'bank_name', 'label' => '계좌 은행명'],
            ['table' => 'system_clients', 'column' => 'bank_name', 'label' => '거래처 은행명'],
            ['table' => 'user_employees', 'column' => 'bank_name', 'label' => '직원 은행명'],
            ['table' => 'ledger_bank_transactions', 'column' => 'bank_name', 'label' => '계좌별거래내역 은행명'],
        ],
        'BANK_ACCOUNT_TYPE' => [
            ['table' => 'system_bank_accounts', 'column' => 'account_type', 'label' => '계좌구분'],
        ],
        'BID_TYPE' => [
            ['table' => 'system_projects', 'column' => 'bid_type', 'label' => '프로젝트 입찰방법'],
        ],
        'BUSINESS_UNIT' => [
            ['table' => 'ledger_transactions', 'column' => 'business_unit', 'label' => '거래 사업구분'],
            ['table' => 'ledger_journal_rules', 'column' => 'business_unit', 'label' => '분개규칙 사업구분'],
        ],
        'CLIENT_CATEGORY' => [
            ['table' => 'system_clients', 'column' => 'client_category', 'label' => '거래처분류'],
        ],
        'CLIENT_TYPE' => [
            ['table' => 'system_clients', 'column' => 'client_type', 'label' => '거래처구분'],
            ['table' => 'system_projects', 'column' => 'client_type', 'label' => '프로젝트 거래처구분'],
            ['table' => 'ledger_journal_rules', 'column' => 'client_type', 'label' => '분개규칙 거래처구분'],
        ],
        'CONSTRUCTION_TYPE' => [
            ['table' => 'system_projects', 'column' => 'construction_type', 'label' => '프로젝트 공사구분'],
        ],
        'CONTRACT_METHOD' => [
            ['table' => 'system_projects', 'column' => 'contract_method', 'label' => '프로젝트 계약방식'],
        ],
        'CONTRACT_TYPE' => [
            ['table' => 'system_projects', 'column' => 'contract_type', 'label' => '프로젝트 계약종류'],
        ],
        'CURRENCY' => [
            ['table' => 'system_bank_accounts', 'column' => 'currency', 'label' => '계좌 통화'],
            ['table' => 'ledger_transactions', 'column' => 'currency', 'label' => '거래 통화'],
            ['table' => 'ledger_vouchers', 'column' => 'currency', 'label' => '전표 통화'],
        ],
        'IMPORT_TYPE' => [
            ['table' => 'ledger_transactions', 'column' => 'import_type', 'label' => '거래 자료유형'],
            ['table' => 'ledger_vouchers', 'column' => 'import_type', 'label' => '전표 자료유형'],
            ['table' => 'ledger_journal_rules', 'column' => 'import_type', 'label' => '분개규칙 자료유형'],
            ['table' => 'ledger_data_imports', 'column' => 'import_type', 'label' => '자료업로드 유형'],
            ['table' => 'ledger_data_sources', 'column' => 'source_type', 'label' => '자료출처 유형'],
            ['table' => 'ledger_import_sources', 'column' => 'source_type', 'label' => '자료출처 유형'],
            ['table' => 'ledger_data_evidences', 'column' => 'source_type', 'label' => '증빙원본 유형'],
        ],
        'PAYMENT_TERM' => [
            ['table' => 'system_clients', 'column' => 'payment_term', 'label' => '거래처 결제조건'],
        ],
        'SOURCE_TYPE' => [
            ['table' => 'ledger_vouchers', 'column' => 'source_type', 'label' => '전표 자료출처'],
            ['table' => 'ledger_transactions', 'column' => 'source_type', 'label' => '거래 자료출처'],
            ['table' => 'ledger_data_sources', 'column' => 'source_type', 'label' => '자료출처'],
            ['table' => 'ledger_import_sources', 'column' => 'source_type', 'label' => '자료출처'],
            ['table' => 'ledger_data_evidences', 'column' => 'source_type', 'label' => '증빙원본 자료출처'],
        ],
        'TAX_TYPE' => [
            ['table' => 'system_clients', 'column' => 'tax_type', 'label' => '거래처 과세구분'],
        ],
        'TRANSACTION_DIRECTION' => [
            ['table' => 'ledger_transactions', 'column' => 'transaction_direction', 'label' => '거래구분'],
            ['table' => 'ledger_journal_rules', 'column' => 'transaction_direction', 'label' => '분개규칙 거래구분'],
            ['table' => 'system_clients', 'column' => 'trade_category', 'label' => '거래처 거래구분'],
        ],
        'TRANSACTION_LINE_TYPE' => [
            ['table' => 'ledger_transaction_lines', 'column' => 'line_type', 'label' => '거래라인 유형'],
        ],
        'TRANSACTION_TYPE' => [
            ['table' => 'ledger_transactions', 'column' => 'transaction_type', 'label' => '거래유형'],
            ['table' => 'ledger_journal_rules', 'column' => 'transaction_type', 'label' => '분개규칙 거래유형'],
        ],
        'WORK_TYPE' => [
            ['table' => 'system_projects', 'column' => 'work_type', 'label' => '프로젝트 공종'],
        ],
    ];

    private const MAPPED_PAYLOAD_TABLES = [
        'ledger_evidence_payloads',
        'ledger_data_import_rows',
        'ledger_data_upload_rows',
        'ledger_import_rows',
        'ledger_processing_items',
    ];

    private const MAPPED_PAYLOAD_KEYS = [
        'BUSINESS_UNIT' => ['business_unit'],
        'CURRENCY' => ['currency'],
        'IMPORT_TYPE' => ['import_type', 'data_type'],
        'SOURCE_TYPE' => ['source_type'],
        'TRANSACTION_DIRECTION' => ['transaction_direction'],
        'TRANSACTION_TYPE' => ['transaction_type'],
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->model = new CodeModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-system.CodeService');
    }

    public function getList(array $filters = []): array
    {
        try {
            return $this->model->getList($filters);
        } catch (\Throwable $e) {
            $this->logger->error('getList() failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function getOptionsByGroup(string $codeGroup): array
    {
        $codeGroup = trim($codeGroup);

        if ($codeGroup === '') {
            return [];
        }

        try {
            return $this->model->getOptionsByGroup($codeGroup);
        } catch (\Throwable $e) {
            $this->logger->error('getOptionsByGroup() failed', [
                'code_group' => $codeGroup,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function getById(string $id): ?array
    {
        try {
            return $this->model->getById($id);
        } catch (\Throwable $e) {
            $this->logger->error('getById() failed', ['id' => $id, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function getGroups(): array
    {
        try {
            return $this->model->getGroups();
        } catch (\Throwable $e) {
            $this->logger->error('getGroups() failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function save(array $data, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        try {
            $this->pdo->beginTransaction();

            $id = trim((string)($data['id'] ?? ''));
            $sortNoProvided = array_key_exists('sort_no', $data) && $data['sort_no'] !== '' && $data['sort_no'] !== null;
            $data = $this->normalize($data);
            $duplicateExcludeId = $id !== '' ? $id : null;

            if ($this->model->existsByGroupAndCode($data['code_group'], $data['code'], $duplicateExcludeId)) {
                throw new \Exception('이미 등록된 코드입니다.');
            }

            if ($id !== '') {
                $before = $this->model->getById($id);
                if (!$before) {
                    throw new \Exception('기준정보를 찾을 수 없습니다.');
                }

                if (!$sortNoProvided) {
                    $data['sort_no'] = (int)($before['sort_no'] ?? 0);
                }

                $this->assertUpdateAllowed($before, $data);

                $data['updated_by'] = $actor;
                unset($data['id']);

                if (!$this->model->updateById($id, $data)) {
                    throw new \Exception('기준정보 수정에 실패했습니다.');
                }

                $this->model->updateGroupNameByCodeGroup($data['code_group'], $data['group_name'], $actor);

                $this->pdo->commit();

                return [
                    'success' => true,
                    'id' => $id,
                    'sort_no' => $data['sort_no'] ?? ($before['sort_no'] ?? null),
                ];
            }

            $newId = UuidHelper::generate();
            $newSortNo = $sortNoProvided
                ? (int)$data['sort_no']
                : SequenceHelper::next('system_codes', 'sort_no');

            $insertData = array_merge($data, [
                'id' => $newId,
                'sort_no' => $newSortNo,
                'created_by' => $actor,
                'updated_by' => $actor,
            ]);

            if (!$this->model->create($insertData)) {
                throw new \Exception('기준정보 등록에 실패했습니다.');
            }

            $this->model->updateGroupNameByCodeGroup($data['code_group'], $data['group_name'], $actor);

            $this->pdo->commit();

            return [
                'success' => true,
                'id' => $newId,
                'sort_no' => $newSortNo,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('save() failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function delete(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        try {
            $row = $this->model->getById($id);
            if (!$row) {
                return ['success' => false, 'message' => '기준정보를 찾을 수 없습니다.'];
            }

            $this->assertDeleteAllowed($row);

            return [
                'success' => $this->model->deleteById($id, $actor),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getTrashList(): array
    {
        try {
            return $this->model->getDeleted();
        } catch (\Throwable $e) {
            $this->logger->error('getTrashList() failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function restore(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        try {
            return [
                'success' => $this->model->restoreById($id, $actor),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function restoreBulk(array $ids, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);
        $count = 0;

        if (empty($ids)) {
            return ['success' => false, 'message' => '복원할 항목을 선택하세요.'];
        }

        foreach ($ids as $id) {
            if ($this->model->restoreById((string)$id, $actor)) {
                $count++;
            }
        }

        return ['success' => true, 'message' => "복원 완료 ({$count}건)"];
    }

    public function restoreAll(string $actorType = 'USER'): array
    {
        $ids = array_column($this->model->getDeleted(), 'id');
        return $this->restoreBulk($ids, $actorType);
    }

    public function purge(string $id, string $actorType = 'USER'): array
    {
        ActorHelper::resolve($actorType);

        try {
            $row = $this->model->getById($id);
            if (!$row) {
                return ['success' => false, 'message' => '기준정보를 찾을 수 없습니다.'];
            }

            $this->assertDeleteAllowed($row, true);

            return ['success' => $this->model->hardDeleteById($id)];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function purgeBulk(array $ids, string $actorType = 'USER'): array
    {
        ActorHelper::resolve($actorType);
        $count = 0;

        if (empty($ids)) {
            return ['success' => false, 'message' => '삭제할 항목을 선택하세요.'];
        }

        $blocked = [];

        foreach ($ids as $id) {
            $result = $this->purge((string)$id, $actorType);
            if (!empty($result['success'])) {
                $count++;
                continue;
            }

            if (!empty($result['message'])) {
                $blocked[] = $result['message'];
            }
        }

        if ($count === 0 && !empty($blocked)) {
            return ['success' => false, 'message' => $blocked[0]];
        }

        return ['success' => true, 'message' => "영구삭제 완료 ({$count}건)"];
    }

    public function purgeAll(string $actorType = 'USER'): array
    {
        return $this->purgeBulk(array_column($this->model->getDeleted(), 'id'), $actorType);
    }

    public function reorder(array $changes): bool
    {
        if (empty($changes)) {
            return true;
        }

        $this->pdo->beginTransaction();

        try {
            foreach ($changes as $row) {
                $this->model->updateSortNo((string)$row['id'], (string)((int)$row['newSortNo'] + 1000000));
            }

            foreach ($changes as $row) {
                $this->model->updateSortNo((string)$row['id'], (string)(int)$row['newSortNo']);
            }

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function normalize(array $data): array
    {
        $data['code_group'] = strtoupper(preg_replace('/\s+/', '', trim((string)($data['code_group'] ?? ''))));
        $data['group_name'] = trim((string)($data['group_name'] ?? ''));
        $data['code'] = strtoupper(trim((string)($data['code'] ?? '')));
        $data['code_name'] = trim((string)($data['code_name'] ?? ''));
        $data['note'] = $this->blankToNull($data['note'] ?? null);
        $data['memo'] = $this->blankToNull($data['memo'] ?? null);
        $data['extra_data'] = $this->normalizeExtraData($data['code_group'], $data['extra_data'] ?? null);
        $data['is_active'] = (int)($data['is_active'] ?? 1);
        $data['sort_no'] = isset($data['sort_no']) && $data['sort_no'] !== ''
            ? (int)$data['sort_no']
            : null;

        if ($data['code_group'] === '' || !preg_match('/^[A-Z_]+$/', $data['code_group'])) {
            throw new \InvalidArgumentException('코드그룹은 영문 대문자와 _만 사용할 수 있습니다.');
        }

        if ($data['group_name'] === '') {
            throw new \InvalidArgumentException('그룹명은 필수입니다.');
        }

        return $data;
    }

    private function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private function normalizeExtraData(string $codeGroup, mixed $value): ?string
    {
        $json = $this->blankToNull($value);
        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new \InvalidArgumentException('추가 속성은 올바른 JSON 객체로 입력해야 합니다.');
        }

        if ($codeGroup === 'BANK') {
            $this->validateBankExtraData($decoded);
        }

        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function validateBankExtraData(array $extraData): void
    {
        $formatValue = null;
        foreach (['account_format', 'account_formats', 'accountNumberFormat', 'account_number_format', 'format', 'formats'] as $key) {
            if (array_key_exists($key, $extraData)) {
                $formatValue = $extraData[$key];
                break;
            }
        }

        if ($formatValue === null) {
            return;
        }

        if (is_string($formatValue)) {
            $this->assertBankFormatPattern($formatValue);
            return;
        }

        if (is_array($formatValue)) {
            if ($this->isListArray($formatValue)) {
                $this->assertBankFormatParts($formatValue);
                return;
            }

            foreach ($formatValue as $length => $pattern) {
                if (!ctype_digit((string)$length) || (int)$length <= 0) {
                    throw new \InvalidArgumentException('은행 계좌번호 포맷의 자릿수 키는 양수 숫자여야 합니다.');
                }

                if (is_string($pattern)) {
                    $this->assertBankFormatPattern($pattern);
                    continue;
                }

                if (is_array($pattern)) {
                    $this->assertBankFormatParts($pattern);
                    continue;
                }

                throw new \InvalidArgumentException('은행 계좌번호 포맷은 문자열 패턴 또는 숫자 배열로 입력해야 합니다.');
            }

            return;
        }

        throw new \InvalidArgumentException('은행 계좌번호 포맷은 문자열, 배열 또는 자릿수별 객체로 입력해야 합니다.');
    }

    private function assertBankFormatPattern(string $pattern): void
    {
        $pattern = trim($pattern);
        if ($pattern === '' || preg_match('/^[#0]+(?:-[#0]+)*$/', $pattern) !== 1) {
            throw new \InvalidArgumentException('은행 계좌번호 포맷은 # 또는 0과 하이픈만 사용할 수 있습니다.');
        }
    }

    private function assertBankFormatParts(array $parts): void
    {
        if (empty($parts)) {
            throw new \InvalidArgumentException('은행 계좌번호 포맷 배열은 비어 있을 수 없습니다.');
        }

        foreach ($parts as $part) {
            if (!is_int($part) && !ctype_digit((string)$part)) {
                throw new \InvalidArgumentException('은행 계좌번호 포맷 배열은 숫자만 입력해야 합니다.');
            }

            if ((int)$part <= 0) {
                throw new \InvalidArgumentException('은행 계좌번호 포맷 배열의 각 값은 양수여야 합니다.');
            }
        }
    }

    private function isListArray(array $values): bool
    {
        return array_keys($values) === range(0, count($values) - 1);
    }

    private function assertUpdateAllowed(array $before, array $after): void
    {
        $references = $this->collectReferences(
            (string)($before['code_group'] ?? ''),
            (string)($before['code'] ?? ''),
            (string)($before['code_name'] ?? '')
        );
        if (empty($references)) {
            return;
        }

        if ((string)($before['code_group'] ?? '') !== (string)($after['code_group'] ?? '')) {
            throw new \RuntimeException($this->buildReferenceMessage('사용 중인 기준정보라 코드그룹을 변경할 수 없습니다.', $references));
        }

        if ((string)($before['code'] ?? '') !== (string)($after['code'] ?? '')) {
            throw new \RuntimeException($this->buildReferenceMessage('사용 중인 기준정보라 코드를 변경할 수 없습니다.', $references));
        }

        if ((string)($before['code_name'] ?? '') !== (string)($after['code_name'] ?? '')) {
            $nameReferences = $this->collectDisplayNameReferences(
                (string)($before['code_group'] ?? ''),
                (string)($before['code_name'] ?? '')
            );

            if (!empty($nameReferences)) {
                throw new \RuntimeException($this->buildReferenceMessage('사용 중인 기준정보라 코드명을 변경할 수 없습니다.', $nameReferences));
            }
        }

        if ((int)($before['is_active'] ?? 1) === 1 && (int)($after['is_active'] ?? 1) === 0) {
            throw new \RuntimeException($this->buildReferenceMessage('사용 중인 기준정보라 미사용으로 변경할 수 없습니다.', $references));
        }
    }

    private function assertDeleteAllowed(array $row, bool $hardDelete = false): void
    {
        $references = $this->collectReferences(
            (string)($row['code_group'] ?? ''),
            (string)($row['code'] ?? ''),
            (string)($row['code_name'] ?? '')
        );
        if (empty($references)) {
            return;
        }

        $message = $hardDelete
            ? '사용 중인 기준정보라 영구삭제할 수 없습니다.'
            : '사용 중인 기준정보라 삭제할 수 없습니다.';

        throw new \RuntimeException($this->buildReferenceMessage($message, $references));
    }

    private function collectReferences(string $codeGroup, string $code, string $codeName = ''): array
    {
        $codeGroup = strtoupper(trim($codeGroup));
        $code = trim($code);
        $codeName = trim($codeName);

        if ($codeGroup === '' || $code === '') {
            return [];
        }

        $references = [];
        $values = [$code];
        if ($codeName !== '' && $codeName !== $code) {
            $values[] = $codeName;
        }

        foreach (self::REFERENCE_MAP[$codeGroup] ?? [] as $target) {
            $count = 0;
            foreach ($values as $value) {
                $count += $this->model->countValueReferences($target['table'], $target['column'], $value);
            }
            if ($count > 0) {
                $references[] = [
                    'label' => $target['label'],
                    'count' => $count,
                ];
            }
        }

        foreach (self::MAPPED_PAYLOAD_KEYS[$codeGroup] ?? [] as $jsonKey) {
            foreach (self::MAPPED_PAYLOAD_TABLES as $table) {
                foreach (['mapped_payload_json', 'mapped_payload'] as $column) {
                    $count = 0;
                    foreach ($values as $value) {
                        $count += $this->model->countJsonValueReferences($table, $column, $jsonKey, $value);
                    }
                    if ($count > 0) {
                        $references[] = [
                            'label' => "증빙 매핑 {$jsonKey}",
                            'count' => $count,
                        ];
                    }
                }
            }
        }

        return $this->mergeReferenceCounts($references);
    }

    private function collectDisplayNameReferences(string $codeGroup, string $codeName): array
    {
        $codeGroup = strtoupper(trim($codeGroup));
        $codeName = trim($codeName);

        if ($codeGroup === '' || $codeName === '') {
            return [];
        }

        $references = [];
        foreach (self::REFERENCE_MAP[$codeGroup] ?? [] as $target) {
            $count = $this->model->countValueReferences($target['table'], $target['column'], $codeName);
            if ($count > 0) {
                $references[] = [
                    'label' => $target['label'],
                    'count' => $count,
                ];
            }
        }

        foreach (self::MAPPED_PAYLOAD_KEYS[$codeGroup] ?? [] as $jsonKey) {
            foreach (self::MAPPED_PAYLOAD_TABLES as $table) {
                foreach (['mapped_payload_json', 'mapped_payload'] as $column) {
                    $count = $this->model->countJsonValueReferences($table, $column, $jsonKey, $codeName);
                    if ($count > 0) {
                        $references[] = [
                            'label' => "증빙 매핑 {$jsonKey}",
                            'count' => $count,
                        ];
                    }
                }
            }
        }

        return $this->mergeReferenceCounts($references);
    }

    private function mergeReferenceCounts(array $references): array
    {
        $merged = [];
        foreach ($references as $reference) {
            $label = (string)($reference['label'] ?? '');
            if ($label === '') {
                continue;
            }

            $merged[$label] = ($merged[$label] ?? 0) + (int)($reference['count'] ?? 0);
        }

        $result = [];
        foreach ($merged as $label => $count) {
            if ($count > 0) {
                $result[] = ['label' => $label, 'count' => $count];
            }
        }

        return $result;
    }

    private function buildReferenceMessage(string $message, array $references): string
    {
        $summary = array_map(
            fn(array $reference) => "{$reference['label']} {$reference['count']}건",
            array_slice($references, 0, 5)
        );

        $remaining = count($references) - count($summary);
        if ($remaining > 0) {
            $summary[] = "외 {$remaining}개";
        }

        return $message . ' 참조: ' . implode(', ', $summary);
    }

    public function downloadTemplate(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('기준정보 업로드');

        $headers = ['코드그룹', '그룹명', '코드', '코드명', '비고', '메모', '사용여부', '추가속성'];
        $sample = ['CLIENT_TYPE', '거래처구분', 'SUPPLIER', '매입처', '거래처구분 예시', '관리자 메모', '1', '{}'];

        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([$sample], null, 'A2');

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="code_template.xlsx"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        exit;
    }

    public function saveFromExcelFile(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $rows = $spreadsheet->getActiveSheet()->toArray(null, false, false, false);

            if (count($rows) < 2) {
                return ['success' => false, 'message' => '업로드할 데이터가 없습니다.'];
            }

            $header = array_map(fn($value) => trim((string)$value), array_shift($rows));
            $map = array_flip($header);
            $count = 0;

            foreach ($rows as $row) {
                if (count(array_filter($row, fn($value) => trim((string)$value) !== '')) === 0) {
                    continue;
                }

                $payload = [
                    'code_group' => trim((string)($row[$map['코드그룹'] ?? -1] ?? '')),
                    'group_name' => trim((string)($row[$map['그룹명'] ?? -1] ?? '')),
                    'code' => trim((string)($row[$map['코드'] ?? -1] ?? '')),
                    'code_name' => trim((string)($row[$map['코드명'] ?? -1] ?? '')),
                    'note' => trim((string)($row[$map['비고'] ?? -1] ?? ($row[$map['설명'] ?? -1] ?? ''))),
                    'memo' => trim((string)($row[$map['메모'] ?? -1] ?? '')),
                    'is_active' => $this->parseActiveValue($row[$map['사용여부'] ?? -1] ?? '1'),
                    'extra_data' => trim((string)($row[$map['추가속성'] ?? -1] ?? '')),
                ];

                if ($payload['code_group'] === '' || $payload['group_name'] === '' || $payload['code'] === '' || $payload['code_name'] === '') {
                    continue;
                }

                $result = $this->save($payload, 'SYSTEM');
                if (!empty($result['success'])) {
                    $count++;
                }
            }

            $spreadsheet->disconnectWorksheets();

            return ['success' => true, 'message' => "{$count}건 업로드되었습니다."];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function downloadExcel(): void
    {
        $rows = $this->model->getList();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('기준정보 목록');

        $sheet->fromArray(['순번', '코드그룹', '그룹명', '코드', '코드명', '비고', '메모', '사용여부', '추가속성'], null, 'A1');

        $rowNo = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([[
                $row['sort_no'] ?? '',
                $row['code_group'] ?? '',
                $row['group_name'] ?? '',
                $row['code'] ?? '',
                $row['code_name'] ?? '',
                $row['note'] ?? '',
                $row['memo'] ?? '',
                (string)($row['is_active'] ?? '1') === '1' ? '사용' : '미사용',
                $row['extra_data'] ?? '',
            ]], null, 'A' . $rowNo);
            $rowNo++;
        }

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="code_list.xlsx"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        exit;
    }

    public function downloadMigrationTemplate(): void
    {
        $this->downloadTemplate();
    }

    public function saveFromMigrationExcelFile(string $filePath): array
    {
        return $this->saveFromExcelFile($filePath);
    }

    public function downloadMigrationExcel(): void
    {
        $this->downloadExcel();
    }

    private function parseActiveValue(mixed $value): int
    {
        $normalized = mb_strtolower(trim((string)$value), 'UTF-8');

        return in_array($normalized, ['1', 'true', 'yes', 'y', 'use', 'active', '사용'], true) ? 1 : 0;
    }
}
