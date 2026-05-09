<?php

namespace App\Controllers\Ledger;

use App\Services\Ledger\JournalRuleService;
use Core\DbPdo;
use PDO;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class JournalRuleController
{
    private JournalRuleService $service;
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbPdo::conn();
        $this->service = new JournalRuleService($this->pdo);
    }

    public function apiList(): void { $this->json(['success' => true, 'data' => $this->service->getList($this->filters())]); }

    public function apiDetail(): void
    {
        $id = trim((string) ($_GET['id'] ?? ''));
        if ($id === '') { $this->json(['success' => false, 'message' => '분개규칙 ID가 없습니다.'], 400); return; }
        $row = $this->service->getById($id, !empty($_GET['include_deleted']));
        $this->json($row ? ['success' => true, 'data' => $row] : ['success' => false, 'message' => '분개규칙을 찾을 수 없습니다.'], $row ? 200 : 404);
    }

    public function apiSave(): void
    {
        try { $this->json($this->service->save($_POST)); }
        catch (\Throwable $e) { $this->json(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function apiStatus(): void
    {
        $id = trim((string) ($_POST['id'] ?? ''));
        $isActive = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 0;

        if ($id === '') {
            $this->json(['success' => false, 'message' => '분개규칙 ID는 필수입니다.'], 400);
            return;
        }

        $this->json($this->service->updateStatus($id, $isActive === 1 ? 1 : 0));
    }

    public function apiReorder(): void
    {
        $input = json_decode(file_get_contents('php://input') ?: '{}', true);
        $changes = is_array($input['changes'] ?? null) ? $input['changes'] : [];

        if ($changes === []) {
            $this->json(['success' => false, 'message' => '정렬 데이터가 없습니다.'], 400);
            return;
        }

        $this->json($this->service->reorder($changes));
    }

    public function apiDelete(): void
    {
        $id = trim((string) ($_POST['id'] ?? ''));
        $this->json($id !== '' ? $this->service->softDelete($id) : ['success' => false, 'message' => '분개규칙 ID가 없습니다.'], $id !== '' ? 200 : 400);
    }

    public function apiTrashList(): void { $this->json(['success' => true, 'data' => $this->service->getTrashList()]); }
    public function apiRestore(): void { $id = trim((string) ($_POST['id'] ?? '')); $this->json($id !== '' ? $this->service->restore($id) : ['success' => false, 'message' => '분개규칙 ID가 없습니다.']); }
    public function apiRestoreBulk(): void { $this->json($this->service->restoreBulk($this->jsonIds())); }
    public function apiRestoreAll(): void { $this->json($this->service->restoreAll()); }
    public function apiPurge(): void { $id = trim((string) ($_POST['id'] ?? '')); $this->json($id !== '' ? $this->service->hardDelete($id) : ['success' => false, 'message' => '분개규칙 ID가 없습니다.']); }
    public function apiPurgeBulk(): void { $this->json($this->service->hardDeleteBulk($this->jsonIds())); }
    public function apiPurgeAll(): void { $this->json($this->service->hardDeleteAll()); }

    public function apiTemplate(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('분개규칙 양식');
        $sheet->fromArray($this->excelHeaders(), null, 'A1');
        $sheet->fromArray([
            ['CONST_MATERIAL_TAX', '공사 재료비 매입', 'CONSTRUCTION', 'GENERAL', 'PURCHASE', 'SUPPLIER', 'TAX_INVOICE', '5100', '2100', '1350', '사용', '공사-국내석 / 외상매입금'],
            ['CONST_LABOR_TAX', '공사 노무비 매입', 'CONSTRUCTION', 'GENERAL', 'PURCHASE', 'SUPPLIER', 'TAX_INVOICE', '5200', '2100', '', '사용', '공사-노무비 / 외상매입금'],
            ['CONST_EXPENSE_TAX', '공사 경비 매입', 'CONSTRUCTION', 'GENERAL', 'PURCHASE', 'SUPPLIER', 'TAX_INVOICE', '5300', '2100', '1350', '사용', '공사-보험료 / 외상매입금'],
        ], null, 'A2');
        $this->autoSize($sheet, 'A', 'L');
        $this->downloadSpreadsheet($spreadsheet, 'journal_rules_template.xlsx');
    }

    public function apiDownloadExcel(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('분개규칙 목록');
        $sheet->fromArray($this->excelHeaders(), null, 'A1');
        $line = 2;
        foreach ($this->service->getList([]) as $row) {
            $sheet->fromArray([[
                $row['rule_code'] ?? '', $row['rule_name'] ?? '', $row['business_unit'] ?? '',
                $row['transaction_type'] ?? '', $row['transaction_direction'] ?? '', $row['client_type'] ?? '',
                $row['import_type'] ?? '', $row['debit_account_code'] ?? '', $row['credit_account_code'] ?? '',
                $row['vat_account_code'] ?? '', ((int) ($row['is_active'] ?? 0) === 1) ? '사용' : '미사용',
                $row['description'] ?? '',
            ]], null, 'A' . $line++);
        }
        $this->autoSize($sheet, 'A', 'L');
        $this->downloadSpreadsheet($spreadsheet, 'journal_rules.xlsx');
    }

    public function apiExcelUpload(): void
    {
        try {
            if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
                $this->json(['success' => false, 'message' => '업로드할 엑셀 파일을 선택하세요.'], 400);
                return;
            }
            $rows = IOFactory::load($_FILES['file']['tmp_name'])->getActiveSheet()->toArray(null, true, true, true);
            $headerMap = $this->headerMap(array_shift($rows) ?: []);
            $saved = 0;
            foreach ($rows as $row) {
                $ruleCode = strtoupper(trim((string) $this->cell($row, $headerMap, ['규칙코드', 'rule_code'])));
                if ($ruleCode === '') continue;
                $payload = [
                    'rule_code' => $ruleCode,
                    'rule_name' => trim((string) $this->cell($row, $headerMap, ['규칙명', 'rule_name'])),
                    'business_unit' => $this->resolveCode('BUSINESS_UNIT', (string) $this->cell($row, $headerMap, ['사업구분', '사업유형', 'business_unit'])),
                    'transaction_type' => $this->resolveCode('TRANSACTION_TYPE', (string) $this->cell($row, $headerMap, ['거래유형', 'transaction_type'])),
                    'transaction_direction' => $this->resolveCode('TRANSACTION_DIRECTION', (string) $this->cell($row, $headerMap, ['거래구분', '거래방향', 'transaction_direction'])),
                    'client_type' => $this->resolveCode('CLIENT_TYPE', (string) $this->cell($row, $headerMap, ['거래처구분', 'client_type'])),
                    'import_type' => $this->resolveCode('IMPORT_TYPE', (string) $this->cell($row, $headerMap, ['자료유형', 'import_type'])),
                    'debit_account_id' => $this->resolveAccountId((string) $this->cell($row, $headerMap, ['차변계정코드', '차변계정', 'debit_account_code'])),
                    'credit_account_id' => $this->resolveAccountId((string) $this->cell($row, $headerMap, ['대변계정코드', '대변계정', 'credit_account_code'])),
                    'vat_account_id' => $this->resolveAccountId((string) $this->cell($row, $headerMap, ['부가세계정코드', '부가세계정', 'vat_account_code']), false),
                    'is_active' => $this->truthy($this->cell($row, $headerMap, ['사용여부', 'is_active'])) ? '1' : '0',
                    'description' => trim((string) $this->cell($row, $headerMap, ['비고', 'description'])),
                ];
                $existing = $this->service->getList([['field' => 'rule_code', 'value' => $ruleCode]]);
                if (!empty($existing[0]['id'])) $payload['id'] = $existing[0]['id'];
                $result = $this->service->save($payload);
                if (empty($result['success'])) throw new \RuntimeException((string) ($result['message'] ?? "{$ruleCode} 저장에 실패했습니다."));
                $saved++;
            }
            $this->json(['success' => true, 'message' => "분개규칙 {$saved}건이 업로드되었습니다."]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    private function excelHeaders(): array
    {
        return ['규칙코드', '규칙명', '사업구분', '거래유형', '거래구분', '거래처구분', '자료유형', '차변계정코드', '대변계정코드', '부가세계정코드', '사용여부', '비고'];
    }

    private function filters(): array
    {
        $filters = json_decode((string) ($_GET['filters'] ?? '[]'), true);
        return is_array($filters) ? $filters : [];
    }

    private function jsonIds(): array
    {
        $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
        return array_values(array_filter(array_map('strval', is_array($payload['ids'] ?? null) ? $payload['ids'] : [])));
    }

    private function downloadSpreadsheet(Spreadsheet $spreadsheet, string $filename): void
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }

    private function autoSize($sheet, string $from, string $to): void
    {
        foreach (range($from, $to) as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    private function headerMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $column => $label) {
            $key = trim((string) $label);
            if ($key !== '') $map[$key] = $column;
        }
        return $map;
    }

    private function cell(array $row, array $headerMap, array $names): mixed
    {
        foreach ($names as $name) if (isset($headerMap[$name])) return $row[$headerMap[$name]] ?? '';
        return '';
    }

    private function resolveCode(string $group, string $value): string
    {
        $raw = trim($value);
        $upper = strtoupper($raw);
        if ($upper === '') return '';
        $stmt = $this->pdo->prepare("
            SELECT code FROM system_codes
            WHERE deleted_at IS NULL AND is_active = 1 AND code_group = :code_group
              AND (UPPER(code) = :upper OR code_name = :raw)
            LIMIT 1
        ");
        $stmt->execute([':code_group' => $group, ':upper' => $upper, ':raw' => $raw]);
        return (string) ($stmt->fetchColumn() ?: $upper);
    }

    private function resolveAccountId(string $value, bool $required = true): ?string
    {
        $value = trim($value);
        if ($value === '') {
            if ($required) throw new \InvalidArgumentException('계정코드는 필수입니다.');
            return null;
        }
        $code = preg_split('/\s+/', $value)[0] ?? $value;
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM ledger_accounts
            WHERE deleted_at IS NULL
              AND (id = :id_value OR account_code = :code OR account_name = :name_value)
            LIMIT 1
        ");
        $stmt->execute([
            ':id_value' => $value,
            ':code' => $code,
            ':name_value' => $value,
        ]);
        $id = $stmt->fetchColumn();
        if (!$id && $required) throw new \InvalidArgumentException("계정을 찾을 수 없습니다: {$value}");
        return $id ? (string) $id : null;
    }

    private function truthy(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'y', 'yes', '사용', 'active'], true);
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
