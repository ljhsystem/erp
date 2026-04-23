<?php

namespace App\Controllers\Ledger;

use App\Models\Ledger\TransactionLinkModel;
use App\Models\Ledger\TransactionModel;
use App\Models\Ledger\VoucherLineModel;
use App\Models\Ledger\VoucherModel;
use App\Services\Ledger\VoucherService;
use Core\DbPdo;
use PDO;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class VoucherController
{
    private PDO $pdo;
    private VoucherService $service;
    private VoucherModel $voucherModel;
    private VoucherLineModel $voucherLineModel;
    private TransactionLinkModel $transactionLinkModel;
    private TransactionModel $transactionModel;

    public function __construct()
    {
        $this->pdo = DbPdo::conn();
        $this->service = new VoucherService($this->pdo);
        $this->voucherModel = new VoucherModel($this->pdo);
        $this->voucherLineModel = new VoucherLineModel($this->pdo);
        $this->transactionLinkModel = new TransactionLinkModel($this->pdo);
        $this->transactionModel = new TransactionModel($this->pdo);
    }

    public function apiList(): void
    {
        $this->jsonResponse(function (): array {
            $filters = [];
            if (!empty($_GET['filters'])) {
                $filters = json_decode((string) $_GET['filters'], true) ?? [];
            }

            return [
                'success' => true,
                'message' => '조회 완료',
                'data' => $this->voucherModel->getList($filters),
            ];
        });
    }

    public function apiTemplate(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('전표 업로드');

        $sheet->fromArray([
            '전표그룹',
            '전표일자',
            '전표상태',
            '타입',
            '적요',
            '비고',
            '메모',
            '계정과목',
            '차변',
            '대변',
            '라인적요',
        ], null, 'A1');

        $sheet->fromArray([
            ['sample-001', date('Y-m-d'), '임시저장', '수동전표', '전표 입력 예시', '', '', '1000', '10000', '0', '차변 라인 예시'],
            ['sample-001', date('Y-m-d'), '임시저장', '수동전표', '전표 입력 예시', '', '', '4100', '0', '10000', '대변 라인 예시'],
        ], null, 'A2');

        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $this->downloadSpreadsheet($spreadsheet, 'voucher_template.xlsx');
    }

    public function apiDownload(): void
    {
        $filters = [];
        if (!empty($_GET['filters'])) {
            $filters = json_decode((string) $_GET['filters'], true) ?? [];
        }

        $rows = $this->voucherModel->getList($filters);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('전표 목록');
        $sheet->fromArray(['전표번호', '전표일자', '상태', '타입', '적요', '비고', '메모', '계정과목', '거래연결여부', '수정일시'], null, 'A1');

        $rowNo = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([[
                $row['voucher_no'] ?? $row['sort_no'] ?? '',
                $row['voucher_date'] ?? '',
                $this->statusLabel((string) ($row['status'] ?? '')),
                $this->typeLabel((string) ($row['type'] ?? $row['ref_type'] ?? '')),
                $row['summary_text'] ?? '',
                $row['note'] ?? '',
                $row['memo'] ?? '',
                $row['account_code'] ?? '',
                ($row['linked_status'] ?? '') === 'linked' ? '연결' : '미연결',
                $row['updated_at'] ?? '',
            ]], null, 'A' . $rowNo);
            $rowNo++;
        }

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $this->downloadSpreadsheet($spreadsheet, 'voucher_list.xlsx');
    }

    public function apiExcelUpload(): void
    {
        $this->jsonResponse(function (): array {
            $file = $_FILES['excel_file'] ?? $_FILES['file'] ?? null;
            if (!$file || empty($file['tmp_name'])) {
                throw new \RuntimeException('업로드 파일이 없습니다.');
            }

            $spreadsheet = IOFactory::load((string) $file['tmp_name']);
            $rows = $spreadsheet->getActiveSheet()->toArray(null, false, false, false);
            if (count($rows) < 2) {
                throw new \RuntimeException('업로드할 데이터가 없습니다.');
            }

            $headerMap = $this->buildHeaderMap(array_shift($rows));
            $groups = [];

            foreach ($rows as $rowIndex => $row) {
                $accountCode = $this->excelCell($row, $headerMap, ['계정과목', 'account_code']);
                $debit = $this->excelAmount($this->excelCell($row, $headerMap, ['차변', 'debit']));
                $credit = $this->excelAmount($this->excelCell($row, $headerMap, ['대변', 'credit']));
                $lineSummary = $this->excelCell($row, $headerMap, ['라인적요', 'line_summary']);

                if ($accountCode === '' && $debit === '0' && $credit === '0' && $lineSummary === '') {
                    continue;
                }

                $groupKey = $this->excelCell($row, $headerMap, ['전표그룹', '전표번호', 'voucher_no', 'sort_no']);
                if ($groupKey === '') {
                    $groupKey = 'row-' . ((int) $rowIndex + 2);
                }

                if (!isset($groups[$groupKey])) {
                    $groups[$groupKey] = [
                        'voucher_date' => $this->excelDate($this->excelCell($row, $headerMap, ['전표일자', 'voucher_date'])),
                        'status' => $this->normalizeImportStatus($this->excelCell($row, $headerMap, ['전표상태', '상태', 'status'])),
                        'type' => $this->normalizeImportType($this->excelCell($row, $headerMap, ['타입', 'type'])),
                        'summary_text' => $this->excelCell($row, $headerMap, ['적요', 'summary_text']),
                        'note' => $this->excelCell($row, $headerMap, ['비고', 'note']),
                        'memo' => $this->excelCell($row, $headerMap, ['메모', 'memo']),
                        'lines' => [],
                    ];
                }

                $groups[$groupKey]['lines'][] = [
                    'account_code' => $accountCode,
                    'debit' => $debit,
                    'credit' => $credit,
                    'line_summary' => $lineSummary,
                ];
            }

            $count = 0;
            foreach ($groups as $payload) {
                if (empty($payload['lines'])) {
                    continue;
                }
                $this->service->save($payload);
                $count++;
            }

            return [
                'success' => true,
                'message' => "{$count}건 업로드되었습니다.",
                'count' => $count,
            ];
        });
    }

    public function apiReorder(): void
    {
        $this->jsonResponse(function (): array {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $changes = $input['changes'] ?? [];

            if ($changes === []) {
                return [
                    'success' => false,
                    'message' => '정렬 데이터가 없습니다.',
                ];
            }

            $this->service->reorder($changes);

            return [
                'success' => true,
                'message' => '정렬 저장 완료',
            ];
        });
    }

    public function apiDetail(): void
    {
        $this->jsonResponse(function (): array {
            $id = trim((string) ($_GET['id'] ?? ''));
            if ($id === '') {
                return [
                    'success' => false,
                    'message' => '전표 ID가 없습니다.',
                ];
            }

            $voucher = $this->voucherModel->getById($id);
            if (!$voucher) {
                return [
                    'success' => false,
                    'message' => '전표를 찾을 수 없습니다.',
                ];
            }

            $voucher['lines'] = $this->voucherLineModel->getByVoucherId($id);
            $voucher['linked_transaction'] = null;

            foreach ($this->transactionLinkModel->getByVoucherId($id) as $link) {
                if (($link['link_type'] ?? '') !== 'MANUAL') {
                    continue;
                }

                $transactionId = trim((string) ($link['transaction_id'] ?? ''));
                if ($transactionId !== '') {
                    $voucher['linked_transaction'] = $this->transactionModel->getById($transactionId);
                }
                break;
            }

            return [
                'success' => true,
                'message' => '조회 완료',
                'data' => $voucher,
            ];
        });
    }

    public function apiTransactionSearch(): void
    {
        $this->jsonResponse(function (): array {
            $query = trim((string) ($_GET['q'] ?? ''));
            $rows = $this->transactionModel->getList([]);

            if ($query !== '') {
                $rows = array_values(array_filter($rows, static function (array $row) use ($query): bool {
                    $haystack = implode(' ', [
                        $row['sort_no'] ?? '',
                        $row['transaction_date'] ?? '',
                        $row['client_name'] ?? '',
                        $row['project_name'] ?? '',
                        $row['item_summary'] ?? '',
                        $row['description'] ?? '',
                        $row['total_amount'] ?? '',
                    ]);

                    return stripos($haystack, $query) !== false;
                }));
            }

            return [
                'success' => true,
                'message' => '조회 완료',
                'data' => array_slice($rows, 0, 50),
            ];
        });
    }

    public function apiSave(): void
    {
        $this->jsonResponse(function (): array {
            $payload = $_POST;
            $payload['lines'] = json_decode((string) ($_POST['lines'] ?? '[]'), true) ?? [];

            $result = $this->service->save($payload);

            return [
                'success' => (bool) ($result['success'] ?? false),
                'message' => ($result['success'] ?? false) ? '저장 완료' : ($result['message'] ?? '저장 실패'),
                'data' => $result,
            ];
        });
    }

    public function apiDelete(): void
    {
        $this->jsonResponse(function (): array {
            $id = trim((string) ($_POST['id'] ?? ''));
            if ($id === '') {
                return [
                    'success' => false,
                    'message' => '전표 ID가 없습니다.',
                ];
            }

            $this->pdo->beginTransaction();

            $this->pdo->prepare("
                UPDATE ledger_transaction_links
                SET is_active = 0,
                    deleted_at = NOW()
                WHERE voucher_id = :voucher_id
                  AND deleted_at IS NULL
            ")->execute([':voucher_id' => $id]);
            $this->voucherLineModel->softDeleteByVoucherId($id, null);
            $success = $this->voucherModel->softDelete($id, null);
            if (!$success) {
                throw new \RuntimeException('전표 삭제에 실패했습니다.');
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '삭제 완료',
            ];
        });
    }

    public function apiTrashList(): void
    {
        $this->jsonResponse(function (): array {
            $stmt = $this->pdo->query("
                SELECT *
                FROM ledger_vouchers
                WHERE deleted_at IS NOT NULL
                ORDER BY deleted_at DESC, sort_no DESC
            ");

            return [
                'success' => true,
                'message' => '조회 완료',
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            ];
        });
    }

    public function apiRestore(): void
    {
        $this->jsonResponse(function (): array {
            $id = trim((string) ($_POST['id'] ?? ''));
            if ($id === '') {
                return [
                    'success' => false,
                    'message' => '전표 ID가 없습니다.',
                ];
            }

            $this->pdo->beginTransaction();
            $this->restoreVoucherById($id);
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '복원 완료',
            ];
        });
    }

    public function apiRestoreBulk(): void
    {
        $this->jsonResponse(function (): array {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $ids = array_values(array_filter((array) ($input['ids'] ?? [])));

            if ($ids === []) {
                return [
                    'success' => false,
                    'message' => '복원할 전표를 선택해 주세요.',
                ];
            }

            $this->pdo->beginTransaction();
            foreach ($ids as $id) {
                $this->restoreVoucherById((string) $id);
            }
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '선택 복원 완료',
            ];
        });
    }

    public function apiRestoreAll(): void
    {
        $this->jsonResponse(function (): array {
            $stmt = $this->pdo->query("
                SELECT id
                FROM ledger_vouchers
                WHERE deleted_at IS NOT NULL
            ");
            $ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'id');

            $this->pdo->beginTransaction();
            foreach ($ids as $id) {
                $this->restoreVoucherById((string) $id);
            }
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '전체 복원 완료',
            ];
        });
    }

    public function apiPurge(): void
    {
        $this->jsonResponse(function (): array {
            $id = trim((string) ($_POST['id'] ?? ''));
            if ($id === '') {
                return [
                    'success' => false,
                    'message' => '전표 ID가 없습니다.',
                ];
            }

            $this->pdo->beginTransaction();
            $this->purgeVoucherById($id);
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '완전 삭제 완료',
            ];
        });
    }

    public function apiPurgeBulk(): void
    {
        $this->jsonResponse(function (): array {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $ids = array_values(array_filter((array) ($input['ids'] ?? [])));

            if ($ids === []) {
                return [
                    'success' => false,
                    'message' => '완전 삭제할 전표를 선택해 주세요.',
                ];
            }

            $this->pdo->beginTransaction();
            foreach ($ids as $id) {
                $this->purgeVoucherById((string) $id);
            }
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '선택 완전 삭제 완료',
            ];
        });
    }

    public function apiPurgeAll(): void
    {
        $this->jsonResponse(function (): array {
            $stmt = $this->pdo->query("
                SELECT id
                FROM ledger_vouchers
                WHERE deleted_at IS NOT NULL
            ");
            $ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'id');

            $this->pdo->beginTransaction();
            foreach ($ids as $id) {
                $this->purgeVoucherById((string) $id);
            }
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '전체 완전 삭제 완료',
            ];
        });
    }

    private function jsonResponse(callable $callback): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            echo json_encode($callback(), JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    private function restoreVoucherById(string $id): void
    {
        if ($id === '') {
            return;
        }

        $this->pdo->prepare("
            UPDATE ledger_transaction_links
            SET is_active = 1,
                deleted_at = NULL,
                deleted_by = NULL
            WHERE voucher_id = :voucher_id
        ")->execute([':voucher_id' => $id]);

        $this->voucherModel->restore($id, null);
        $this->voucherLineModel->restoreByVoucherId($id, null);
    }

    private function purgeVoucherById(string $id): void
    {
        if ($id === '') {
            return;
        }

        $this->pdo->prepare("
            DELETE FROM ledger_transaction_links
            WHERE voucher_id = :voucher_id
        ")->execute([':voucher_id' => $id]);

        $stmt = $this->pdo->prepare("
            SELECT id
            FROM ledger_voucher_lines
            WHERE voucher_id = :voucher_id
        ");
        $stmt->execute([':voucher_id' => $id]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $line) {
            $lineId = trim((string) ($line['id'] ?? ''));
            if ($lineId !== '') {
                $this->voucherLineModel->hardDelete($lineId);
            }
        }

        if (!$this->voucherModel->hardDelete($id)) {
            throw new \RuntimeException('전표 완전 삭제에 실패했습니다.');
        }
    }

    private function downloadSpreadsheet(Spreadsheet $spreadsheet, string $filename): void
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    private function buildHeaderMap(array $headerRow): array
    {
        $headerMap = [];
        foreach ($headerRow as $column => $label) {
            $label = trim((string) $label);
            if ($label !== '') {
                $headerMap[$label] = $column;
            }
        }

        return $headerMap;
    }

    private function excelCell(array $row, array $headerMap, array $aliases): string
    {
        foreach ($aliases as $alias) {
            if (!isset($headerMap[$alias])) {
                continue;
            }

            $column = $headerMap[$alias];
            $value = $row[$column] ?? '';

            return trim((string) $value);
        }

        return '';
    }

    private function excelDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return date('Y-m-d');
        }

        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $timestamp = strtotime($value);

        return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
    }

    private function excelAmount(string $value): string
    {
        $normalized = preg_replace('/[^0-9.-]/', '', str_replace(',', '', $value));

        if ($normalized === '' || $normalized === '-' || $normalized === '.') {
            return '0';
        }

        return (string) max(0, (float) $normalized);
    }

    private function normalizeImportStatus(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');

        return match ($value) {
            '확정', 'posted', 'approved' => 'posted',
            '마감', 'locked' => 'locked',
            '삭제', 'deleted' => 'deleted',
            default => 'draft',
        };
    }

    private function normalizeImportType(string $value): string
    {
        $value = mb_strtoupper(trim($value), 'UTF-8');

        return match ($value) {
            '자동전표', 'AUTO' => 'AUTO',
            '조정전표', 'ADJUST' => 'ADJUST',
            '결산전표', 'CLOSING' => 'CLOSING',
            default => 'MANUAL',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'posted' => '확정',
            'locked' => '마감',
            'deleted' => '삭제',
            default => '임시저장',
        };
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'AUTO' => '자동전표',
            'ADJUST' => '조정전표',
            'CLOSING' => '결산전표',
            default => '수동전표',
        };
    }
}
