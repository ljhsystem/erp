<?php

namespace App\Services\Ledger;

use Core\Helpers\ActorHelper;
use PDO;

class BundledVoucherService
{
    public function __construct(private PDO $pdo, private array $callbacks = [])
    {
    }

    public function createBundledVoucherFromEvidenceRows(array $rowIds): array
    {
        $rowIds = array_values(array_filter(array_unique(array_map('strval', $rowIds))));
        if (count($rowIds) < 2) {
            return ['success' => false, 'message' => '묶음 전표는 2건 이상의 증빙을 선택해야 생성할 수 있습니다.'];
        }
        if (!$this->call('tableExists', 'ledger_evidence_payloads')) {
            return ['success' => false, 'message' => '증빙 payload 테이블이 없어 묶음 전표를 생성할 수 없습니다.'];
        }

        [$inSql, $params] = $this->call('placeholdersForIds', $rowIds, 'bundled_voucher_evidence');
        $stmt = $this->pdo->prepare("
            SELECT
                p.evidence_id AS id,
                p.evidence_type AS source_type,
                JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.evidence_date')) AS evidence_date,
                JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.client_id')) AS client_id,
                JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.project_id')) AS project_id,
                JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.employee_id')) AS employee_id,
                JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.bank_account_id')) AS bank_account_id,
                JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.card_id')) AS card_id,
                JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.client_name')) AS client_name,
                JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.project_name')) AS project_name,
                JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.employee_name')) AS employee_name,
                JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.bank_account_name')) AS bank_account_name,
                JSON_UNQUOTE(JSON_EXTRACT(p.mapped_payload_json, '$.card_name')) AS card_name,
                p.mapped_payload_json,
                tx.target_id AS transaction_id
            FROM ledger_evidence_payloads p
            LEFT JOIN ledger_evidence_processing pr
                ON pr.evidence_type = p.evidence_type
               AND pr.evidence_id = p.evidence_id
               AND pr.deleted_at IS NULL
            LEFT JOIN ledger_evidence_links tx
                ON tx.evidence_type = p.evidence_type
               AND tx.evidence_id = p.evidence_id
               AND tx.target_type = 'TRANSACTION'
               AND tx.deleted_at IS NULL
            WHERE p.evidence_id IN ({$inSql})
              AND p.deleted_at IS NULL
        ");
        $stmt->execute($params);

        $rowsById = [];
        foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
            $rowsById[(string) ($row['id'] ?? '')] = $row;
        }

        $rows = [];
        foreach ($rowIds as $id) {
            if (isset($rowsById[$id])) {
                $rows[] = $rowsById[$id];
            }
        }
        if (count($rows) !== count($rowIds)) {
            return ['success' => false, 'message' => '선택한 증빙 중 일부를 찾을 수 없어 묶음 전표를 생성할 수 없습니다.'];
        }

        $actor = ActorHelper::user();
        $errors = [];
        $voucherLines = [];
        $voucherDate = '';
        $firstEvidenceId = (string) ($rows[0]['id'] ?? '');
        $linkedTransactions = [];

        foreach ($rows as $index => $row) {
            $rowNo = $index + 1;
            $evidenceId = (string) ($row['id'] ?? '');
            $transactionId = trim((string) ($row['transaction_id'] ?? ''));
            $mappedPayload = $row['mapped_payload_json'] ?? null;
            $mapped = is_array($mappedPayload) ? $mappedPayload : (is_string($mappedPayload) && trim($mappedPayload) !== '' ? json_decode($mappedPayload, true) : null);
            if (!is_array($mapped)) {
                $errors[] = $rowNo . '번째 증빙의 매핑 payload를 읽을 수 없습니다.';
                continue;
            }

            $dataType = $this->call('normalizeDataType', (string) ($row['source_type'] ?? $mapped['import_type'] ?? ''));
            if ($dataType === 'BANK_TRANSACTION') {
                $mapped = $this->call('normalizeBankTransactionPayload', $mapped);
            }
            $mapped = $this->call('normalizeEvidenceMappedPayloadForResponse', $mapped);
            $mapped = $this->call('mergeEvidenceBusinessInfoIntoPayload', $row, $mapped);
            $mapped['source_type'] = $dataType;
            $mapped['import_type'] = $dataType;
            $mapped['evidence_date'] = $row['evidence_date'] ?? ($mapped['evidence_date'] ?? '');

            if ($this->call('activeVoucherExistsForEvidence', $evidenceId, $transactionId)) {
                $errors[] = $rowNo . '번째 증빙은 이미 활성 전표와 연결되어 있어 묶음 전표를 생성할 수 없습니다.';
                continue;
            }

            $readiness = $this->call('readinessForEvidenceRow', [
                'source_type' => $dataType,
                'import_type' => $dataType,
                'source_key' => $mapped['source_key'] ?? '',
                'evidence_date' => $row['evidence_date'] ?? '',
            ], $mapped);
            if (($readiness['status'] ?? '') !== 'READY') {
                $errors[] = $rowNo . '번째 증빙은 전표 생성 준비가 완료되지 않았습니다. ' . implode(' / ', $readiness['errors'] ?? []);
                continue;
            }

            if (!$this->call('hasVoucherLinesPayload', $mapped)) {
                $errors[] = $rowNo . '번째 증빙에 전표 라인 정보가 없어 묶음 전표를 생성할 수 없습니다.';
                continue;
            }

            try {
                $lines = $this->call('bankVoucherLinesForSave', $mapped['_voucher_lines'] ?? []);
                $missingRefMessage = $this->call('missingRequiredEvidenceRefsMessage', $lines, $mapped);
                if ($missingRefMessage !== null) {
                    $errors[] = $rowNo . '번째 증빙의 필수 참조값이 누락되었습니다. ' . $missingRefMessage;
                    continue;
                }
                $lines = $this->call('applyEvidenceRefsToVoucherLines', $lines, $mapped);
                $voucherLines = array_merge($voucherLines, $lines);
            } catch (\Throwable $e) {
                $errors[] = $rowNo . '번째 증빙의 전표 라인을 처리하는 중 오류가 발생했습니다. ' . $e->getMessage();
                continue;
            }

            if ($voucherDate === '') {
                $voucherDate = $this->call('dateValue', $mapped['voucher_date'] ?? $mapped['transaction_date'] ?? $mapped['evidence_date'] ?? '');
            }
            if ($transactionId !== '') {
                $linkedTransactions[$evidenceId] = $transactionId;
            }
        }

        if ($errors !== []) {
            return ['success' => false, 'message' => implode("\n", $errors), 'errors' => $errors];
        }
        if ($voucherLines === []) {
            return ['success' => false, 'message' => '묶음 전표에 저장할 전표 라인이 없어 전표를 생성할 수 없습니다.'];
        }

        try {
            $result = $this->call('saveVoucher', [
                'voucher_date' => $voucherDate !== '' ? $voucherDate : date('Y-m-d'),
                'summary_text' => '묶음 증빙 ' . count($rows) . '건',
                'source_type' => 'SYSTEM',
                'lines' => $voucherLines,
                'payments' => [],
            ]);
            $voucherId = (string) ($result['voucher_id'] ?? $result['id'] ?? '');
            if ($voucherId === '') {
                return ['success' => false, 'message' => '묶음 전표 저장은 완료되었지만 생성된 전표 ID를 확인할 수 없습니다.'];
            }

            $this->tagBundledVoucher($voucherId, $firstEvidenceId, $actor);
            foreach ($rows as $row) {
                $evidenceId = (string) ($row['id'] ?? '');
                $transactionId = $linkedTransactions[$evidenceId] ?? '';
                $this->call('linkVoucherToEvidence', $evidenceId, $voucherId, $transactionId, $actor);
                if ($transactionId !== '') {
                    $this->call('linkVoucherToTransaction', $voucherId, $transactionId, null, 'AUTO', $actor);
                }
                $this->call('updateEvidenceVoucherStatus', $evidenceId, 'CREATED', $actor);
            }

            return [
                'success' => true,
                'voucher_id' => $voucherId,
                'processed_ids' => $rowIds,
                'success_count' => count($rows),
                'message' => '선택한 증빙 ' . count($rows) . '건으로 묶음 전표를 생성했습니다.',
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function tagBundledVoucher(string $voucherId, string $firstEvidenceId, string $actor): void
    {
        if ($voucherId === '') {
            return;
        }

        $sets = [];
        $params = [':id' => $voucherId, ':actor' => $actor];
        if ($this->call('tableColumnExists', 'ledger_vouchers', 'source_type')) {
            $sets[] = 'source_type = :source_type';
            $params[':source_type'] = 'SYSTEM';
        }
        if ($firstEvidenceId !== '' && $this->call('tableColumnExists', 'ledger_vouchers', 'source_id')) {
            $sets[] = 'source_id = :source_id';
            $params[':source_id'] = $firstEvidenceId;
        }
        if ($this->call('tableColumnExists', 'ledger_vouchers', 'import_type')) {
            $sets[] = 'import_type = :import_type';
            $params[':import_type'] = 'BUNDLED_EVIDENCE';
        }
        if ($this->call('tableColumnExists', 'ledger_vouchers', 'updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }
        if ($this->call('tableColumnExists', 'ledger_vouchers', 'updated_by')) {
            $sets[] = 'updated_by = :actor';
        }
        if ($sets === []) {
            return;
        }

        $this->pdo->prepare('UPDATE ledger_vouchers SET ' . implode(', ', $sets) . ' WHERE id = :id')
            ->execute($params);
    }

    private function call(string $name, mixed ...$args): mixed
    {
        $callback = $this->callbacks[$name] ?? null;
        if (!is_callable($callback)) {
            throw new \RuntimeException('BundledVoucherService callback missing: ' . $name);
        }

        return $callback(...$args);
    }
}
