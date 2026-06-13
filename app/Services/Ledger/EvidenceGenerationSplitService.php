<?php

namespace App\Services\Ledger;

use App\Models\Ledger\ProcessingItemModel;
use App\Services\Ledger\ProcessingItemSplitService;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PDO;

class EvidenceGenerationSplitService
{
    /**
     * @param callable(array):array $mappedPayloadForStorage
     * @param callable(mixed):?float $amountOrNull
     * @param callable(string):string $normalizeDataType
     */
    public function __construct(
        private PDO $pdo,
        private $mappedPayloadForStorage,
        private $amountOrNull,
        private $normalizeDataType
    ) {
    }

    private function normalizeDataType(string $type): string
    {
        return ($this->normalizeDataType)($type);
    }

    public function splitChild(array $payload): array
    {
        $evidenceId = trim((string) ($payload['evidence_id'] ?? $payload['id'] ?? ''));
        $processingItemId = trim((string) ($payload['processing_item_id'] ?? ''));
        if ($evidenceId === '' && $processingItemId === '') {
            return ['payload' => ['success' => false, 'message' => '분할할 증빙원본을 선택해주세요.'], 'status' => 400];
        }

        $itemModel = new ProcessingItemModel($this->pdo);
        $parentItem = null;
        if ($processingItemId !== '') {
            $parentItem = $itemModel->getById($processingItemId);
            if ($parentItem && isset($payload['children']) && is_array($payload['children'])) {
                $ancestorId = trim((string) ($parentItem['parent_item_id'] ?? ''));
                if ($ancestorId !== '') {
                    $parentItem = $itemModel->getById($ancestorId) ?: $parentItem;
                }
            }
        }

        if (!$parentItem) {
            $stmt = $this->pdo->prepare("
                SELECT *
                FROM ledger_data_evidences
                WHERE id = :id
                  AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([':id' => $evidenceId]);
            $evidence = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$evidence) {
                return ['payload' => ['success' => false, 'message' => '증빙원본을 찾을 수 없습니다.'], 'status' => 404];
            }
            $parentItem = $itemModel->ensureDefaultItemForEvidence($evidence);
        }

        if (!$parentItem) {
            return ['payload' => ['success' => false, 'message' => '분할 기준 처리항목을 만들 수 없습니다.'], 'status' => 400];
        }

        if (isset($payload['children']) && is_array($payload['children'])) {
            $result = $this->saveProcessingItemSplitChildren($parentItem, $payload['children']);
            return ['payload' => $result, 'status' => !empty($result['success']) ? 200 : 422];
        }

        $parentPayload = json_decode((string) ($parentItem['mapped_payload_json'] ?? ''), true);
        $parentPayload = is_array($parentPayload) ? $parentPayload : [];
        $blankPayload = $parentPayload;
        foreach (['quantity', 'unit_price', 'supply_amount', 'vat_amount', 'total_amount', 'amount', 'deposit_amount', 'withdraw_amount', 'deposit', 'withdraw'] as $key) {
            if (array_key_exists($key, $blankPayload)) {
                $blankPayload[$key] = 0;
            }
        }
        foreach (['description', 'memo', 'item_name', 'line_summary'] as $key) {
            if (array_key_exists($key, $blankPayload)) {
                $blankPayload[$key] = '';
            }
        }

        $children = [
            [
                'sort_no' => 1,
                'item_type' => 'SPLIT',
                'line_type' => $parentItem['line_type'] ?? $parentItem['source_type'] ?? null,
                'quantity' => $parentItem['quantity'] ?? null,
                'unit_price' => $parentItem['unit_price'] ?? null,
                'supply_amount' => $parentItem['supply_amount'] ?? null,
                'vat_amount' => $parentItem['vat_amount'] ?? null,
                'total_amount' => $parentItem['total_amount'] ?? null,
                'currency' => $parentItem['currency'] ?? 'KRW',
                'description' => $parentItem['description'] ?? null,
                'mapped_payload' => $parentPayload,
            ],
            [
                'sort_no' => 2,
                'item_type' => 'SPLIT',
                'line_type' => $parentItem['line_type'] ?? $parentItem['source_type'] ?? null,
                'quantity' => null,
                'unit_price' => null,
                'supply_amount' => 0,
                'vat_amount' => 0,
                'total_amount' => 0,
                'deposit_amount' => 0,
                'withdraw_amount' => 0,
                'currency' => $parentItem['currency'] ?? 'KRW',
                'description' => null,
                'mapped_payload' => $blankPayload,
            ],
        ];

        $result = (new ProcessingItemSplitService($this->pdo, $itemModel))
            ->split((string) ($parentItem['id'] ?? ''), $children, '증빙원본 자식행 추가', ActorHelper::user());

        return ['payload' => $result + ['message' => ($result['message'] ?? '자식행이 추가되었습니다.')], 'status' => !empty($result['success']) ? 200 : 422];
    }

    public function deleteProcessingChild(array $payload): array
    {
        $processingItemId = trim((string) ($payload['processing_item_id'] ?? $payload['id'] ?? ''));
        if ($processingItemId === '') {
            return ['payload' => ['success' => false, 'message' => '삭제할 자식행을 선택해주세요.'], 'status' => 400];
        }

        $itemModel = new ProcessingItemModel($this->pdo);
        $item = $itemModel->getById($processingItemId);
        if (!$item) {
            return ['payload' => ['success' => false, 'message' => '자식행을 찾을 수 없습니다.'], 'status' => 404];
        }
        if (trim((string) ($item['parent_item_id'] ?? '')) === '') {
            return ['payload' => ['success' => false, 'message' => '부모행은 이 버튼으로 삭제할 수 없습니다.'], 'status' => 400];
        }

        $stmt = $this->pdo->prepare('DELETE FROM ledger_processing_items WHERE id = :id AND parent_item_id IS NOT NULL');
        $stmt->execute([':id' => $processingItemId]);

        return ['payload' => ['success' => true, 'message' => '자식행을 삭제했습니다.']];
    }

    public function updateProcessingChild(array $payload): array
    {
        $processingItemId = trim((string) ($payload['processing_item_id'] ?? $payload['id'] ?? ''));
        $child = $payload['child'] ?? [];
        if (!is_array($child)) {
            $child = [];
        }
        if ($processingItemId === '') {
            $processingItemId = trim((string) ($child['id'] ?? $child['processing_item_id'] ?? ''));
        }
        if ($processingItemId === '') {
            return ['payload' => ['success' => false, 'message' => '수정할 자식행을 선택해주세요.'], 'status' => 400];
        }

        $itemModel = new ProcessingItemModel($this->pdo);
        $item = $itemModel->getById($processingItemId);
        if (!$item) {
            return ['payload' => ['success' => false, 'message' => '자식행을 찾을 수 없습니다.'], 'status' => 404];
        }
        $parentItemId = trim((string) ($item['parent_item_id'] ?? ''));
        if ($parentItemId === '') {
            return ['payload' => ['success' => false, 'message' => '부모행은 자식 수정 모달에서 수정할 수 없습니다.'], 'status' => 400];
        }
        $parentItem = $itemModel->getById($parentItemId);
        if (!$parentItem) {
            return ['payload' => ['success' => false, 'message' => '부모행을 찾을 수 없습니다.'], 'status' => 404];
        }

        $payloadJson = $child['mapped_payload'] ?? [];
        if (!is_array($payloadJson)) {
            $payloadJson = [];
        }
        $payloadJson = ($this->mappedPayloadForStorage)($payloadJson);
        $child['mapped_payload'] = $payloadJson;
        $missingRequired = $this->processingSplitRequiredMissingMessages($parentItem, [$child + ['mapped_payload' => $payloadJson]]);
        if ($missingRequired !== []) {
            return ['payload' => [
                'success' => false,
                'message' => '필수 항목을 입력해야 저장할 수 있습니다. ' . implode(', ', array_slice($missingRequired, 0, 5)) . (count($missingRequired) > 5 ? ' 외 ' . (count($missingRequired) - 5) . '건' : ''),
            ], 'status' => 422];
        }

        $actor = ActorHelper::user();
        $timestamp = date('Y-m-d H:i:s');
        $itemModel->update($processingItemId, [
            'quantity' => ($this->amountOrNull)($child['quantity'] ?? $payloadJson['quantity'] ?? null),
            'unit_price' => ($this->amountOrNull)($child['unit_price'] ?? $payloadJson['unit_price'] ?? null),
            'supply_amount' => ($this->amountOrNull)($child['supply_amount'] ?? $payloadJson['supply_amount'] ?? null),
            'vat_amount' => ($this->amountOrNull)($child['vat_amount'] ?? $payloadJson['vat_amount'] ?? null),
            'total_amount' => ($this->amountOrNull)($child['total_amount'] ?? $payloadJson['total_amount'] ?? null),
            'description' => $child['description'] ?? ($payloadJson['description'] ?? null),
            'memo' => $child['memo'] ?? ($payloadJson['memo'] ?? null),
            'mapped_payload_json' => json_encode($payloadJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => $timestamp,
            'updated_by' => $actor,
        ]);

        return ['payload' => ['success' => true, 'message' => '자식 항목을 수정했습니다.']];
    }

    private function saveProcessingItemSplitChildren(array $parentItem, array $children): array
    {
        $parentItemId = trim((string) ($parentItem['id'] ?? ''));
        if ($parentItemId === '') {
            return ['success' => false, 'message' => '분할 기준 처리항목이 없습니다.'];
        }

        $children = array_values(array_filter($children, static fn($child): bool => is_array($child)));
        if (count($children) < 2) {
            return ['success' => false, 'message' => '분할 항목은 2개 이상 필요합니다.'];
        }

        foreach ($children as &$child) {
            $payload = $child['mapped_payload'] ?? [];
            $child['mapped_payload'] = is_array($payload)
                ? ($this->mappedPayloadForStorage)($payload)
                : [];
        }
        unset($child);

        foreach ($this->processingSplitAmountFields($parentItem, $children) as $field => $parentAmount) {
            $sum = 0.0;
            foreach ($children as $child) {
                $sum += (float) ($this->processingSplitChildAmountValue($child, $field) ?? 0);
            }
            if (abs((float) $parentAmount - $sum) > 0.01) {
                return [
                    'success' => false,
                    'message' => '분할 합계가 부모 ' . $field . ' 금액과 일치하지 않습니다. 부모=' . $this->formatAmountForMessage((float) $parentAmount) . ' 자식합계=' . $this->formatAmountForMessage($sum),
                ];
            }
        }
        $missingRequired = $this->processingSplitRequiredMissingMessages($parentItem, $children);
        if ($missingRequired !== []) {
            return [
                'success' => false,
                'message' => '필수 항목을 입력해야 저장할 수 있습니다. ' . implode(', ', array_slice($missingRequired, 0, 5)) . (count($missingRequired) > 5 ? ' 외 ' . (count($missingRequired) - 5) . '건' : ''),
            ];
        }

        $itemModel = new ProcessingItemModel($this->pdo);
        $existingChildren = [];
        foreach ($itemModel->getBySource((string) ($parentItem['source_table'] ?? ''), (string) ($parentItem['source_id'] ?? '')) as $item) {
            if (trim((string) ($item['parent_item_id'] ?? '')) === $parentItemId) {
                $existingChildren[(string) ($item['id'] ?? '')] = $item;
            }
        }

        $actor = ActorHelper::user();
        $parentDisplayPath = $this->processingParentDisplayPath($parentItem);
        $timestamp = date('Y-m-d H:i:s');
        $splitGroupId = trim((string) ($parentItem['split_group_id'] ?? '')) ?: UuidHelper::generate();
        $saved = [];

        $this->pdo->beginTransaction();
        try {
            $itemModel->update($parentItemId, [
                'item_status' => 'SPLIT',
                'is_current' => 0,
                'split_group_id' => $splitGroupId,
                'sort_no' => ctype_digit($parentDisplayPath) ? (int) $parentDisplayPath : (int) ($parentItem['sort_no'] ?? 1),
                'display_path' => $parentDisplayPath,
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ]);

            foreach ($children as $index => $child) {
                $sortNo = max(1, (int) ($child['sort_no'] ?? ($index + 1)));
                $childId = trim((string) ($child['id'] ?? $child['processing_item_id'] ?? ''));
                $existing = $childId !== '' ? ($existingChildren[$childId] ?? null) : null;
                $payload = $child['mapped_payload'] ?? [];
                if (!is_array($payload)) {
                    $payload = [];
                }
                $data = [
                    'sort_no' => $sortNo,
                    'display_path' => $parentDisplayPath . '-' . $sortNo,
                    'quantity' => ($this->amountOrNull)($child['quantity'] ?? $payload['quantity'] ?? null),
                    'unit_price' => ($this->amountOrNull)($child['unit_price'] ?? $payload['unit_price'] ?? null),
                    'supply_amount' => ($this->amountOrNull)($child['supply_amount'] ?? $payload['supply_amount'] ?? null),
                    'vat_amount' => ($this->amountOrNull)($child['vat_amount'] ?? $payload['vat_amount'] ?? null),
                    'total_amount' => ($this->amountOrNull)($child['total_amount'] ?? $payload['total_amount'] ?? null),
                    'description' => $child['description'] ?? ($payload['description'] ?? null),
                    'memo' => $child['memo'] ?? ($payload['memo'] ?? null),
                    'mapped_payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'item_status' => 'ACTIVE',
                    'is_current' => 1,
                    'updated_at' => $timestamp,
                    'updated_by' => $actor,
                ];

                if ($existing) {
                    $itemModel->update($childId, $data);
                    $saved[] = $itemModel->getById($childId) ?: ($data + ['id' => $childId]);
                    unset($existingChildren[$childId]);
                    continue;
                }

                $newId = UuidHelper::generate();
                $insert = array_merge($this->processingChildBasePayload($parentItem), $data, [
                    'id' => $newId,
                    'parent_item_id' => $parentItemId,
                    'source_item_id' => $parentItemId,
                    'lineage_root_id' => $parentItem['lineage_root_id'] ?? $parentItemId,
                    'split_group_id' => $splitGroupId,
                    'item_type' => 'SPLIT',
                    'created_at' => $timestamp,
                    'created_by' => $actor,
                ]);
                $itemModel->insert($insert);
                $saved[] = $itemModel->getById($newId) ?: $insert;
            }

            foreach (array_keys($existingChildren) as $deletedChildId) {
                $itemModel->update($deletedChildId, [
                    'deleted_at' => $timestamp,
                    'deleted_by' => $actor,
                    'is_current' => 0,
                    'item_status' => 'DELETED',
                    'updated_at' => $timestamp,
                    'updated_by' => $actor,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true, 'children' => $saved, 'message' => '분할 항목을 저장했습니다.'];
    }

    private function processingParentDisplayPath(array $parentItem): string
    {
        if (($parentItem['source_table'] ?? '') === 'ledger_data_evidences') {
            $sourceId = trim((string) ($parentItem['source_id'] ?? ''));
            $sourceType = trim((string) ($parentItem['source_type'] ?? ''));
            if ($sourceId !== '' && $sourceType !== '') {
                $stmt = $this->pdo->prepare("
                    SELECT mapped_payload_json
                    FROM ledger_evidence_payloads
                    WHERE evidence_type = :evidence_type
                      AND evidence_id = :evidence_id
                      AND deleted_at IS NULL
                    LIMIT 1
                ");
                $stmt->execute([
                    ':evidence_type' => $sourceType,
                    ':evidence_id' => $sourceId,
                ]);
                $mapped = json_decode((string) ($stmt->fetchColumn() ?: ''), true);
                if (is_array($mapped)) {
                    foreach (['_status_sort_no', '_create_sort_no', '_row_no', '_upload_row_no'] as $key) {
                        $value = trim((string) ($mapped[$key] ?? ''));
                        if ($value !== '' && $value !== '0') {
                            return $value;
                        }
                    }
                }
            }
        }

        $displayPath = trim((string) ($parentItem['display_path'] ?? ''));
        if ($displayPath !== '') {
            return $displayPath;
        }
        $sortNo = trim((string) ($parentItem['sort_no'] ?? ''));
        return $sortNo !== '' && $sortNo !== '0' ? $sortNo : '1';
    }

    private function processingSplitRequiredMissingMessages(array $parentItem, array $children): array
    {
        return [];
    }

    private function processingSplitAmountFields(array $parentItem, array $children): array
    {
        $sourceType = $this->normalizeDataType((string) ($parentItem['source_type'] ?? ''));
        $parentPayload = json_decode((string) ($parentItem['mapped_payload_json'] ?? ''), true);
        $parentPayload = is_array($parentPayload) ? $parentPayload : [];
        if ($sourceType === 'BANK_TRANSACTION') {
            $fields = [];
            foreach (['deposit_amount', 'withdraw_amount'] as $field) {
                $value = $this->processingSplitParentAmountValue($parentItem, $parentPayload, $field);
                if ($value !== null) {
                    $fields[$field] = $value;
                }
            }
            return $fields;
        }
        $fields = [];
        foreach (array_keys($parentPayload) as $field) {
            if ($this->isProcessingSplitAmountField((string) $field) && !$this->isProcessingSplitExcludedField($sourceType, (string) $field, (string) $field)) {
                $value = ($this->amountOrNull)($parentPayload[$field] ?? null);
                if ($value !== null) {
                    $fields[(string) $field] = $value;
                }
            }
        }
        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }
            $payload = is_array($child['mapped_payload'] ?? null) ? $child['mapped_payload'] : [];
            foreach (array_keys($payload) as $field) {
                $field = (string) $field;
                if (isset($fields[$field]) || !$this->isProcessingSplitAmountField($field) || $this->isProcessingSplitExcludedField($sourceType, $field, $field)) {
                    continue;
                }
                $value = ($this->amountOrNull)($parentPayload[$field] ?? $parentItem[$field] ?? null);
                if ($value !== null) {
                    $fields[$field] = $value;
                }
            }
        }
        foreach (['supply_amount', 'vat_amount', 'total_amount'] as $field) {
            if (!isset($fields[$field]) && array_key_exists($field, $parentPayload)) {
                $value = ($this->amountOrNull)($parentPayload[$field] ?? $parentItem[$field] ?? null);
                if ($value !== null) {
                    $fields[$field] = $value;
                }
            }
        }
        return $fields;
    }

    private function processingSplitParentAmountValue(array $parentItem, array $parentPayload, string $field): ?float
    {
        $keys = $field === 'withdraw_amount'
            ? ['withdraw_amount', 'withdrawal_amount']
            : [$field];
        foreach ($keys as $key) {
            $value = ($this->amountOrNull)($parentPayload[$key] ?? $parentItem[$key] ?? null);
            if ($value !== null) {
                return abs((float) $value);
            }
        }
        return null;
    }

    private function processingSplitChildAmountValue(array $child, string $field): ?float
    {
        $payload = is_array($child['mapped_payload'] ?? null) ? $child['mapped_payload'] : [];
        $keys = $field === 'withdraw_amount'
            ? ['withdraw_amount', 'withdrawal_amount']
            : [$field];
        foreach ($keys as $key) {
            $value = ($this->amountOrNull)($child[$key] ?? $payload[$key] ?? null);
            if ($value !== null) {
                return abs((float) $value);
            }
        }
        return null;
    }

    private function isProcessingSplitExcludedField(string $sourceType, string $field, string $label = ''): bool
    {
        $sourceType = $this->normalizeDataType($sourceType);
        $fieldText = strtolower(trim($field));
        $labelText = trim($label);
        $text = $fieldText . ' ' . $labelText;
        if (preg_match('/balance_amount|check_bill_amount|balance|거래후잔액|잔액|수표어음/u', $text)) {
            return true;
        }
        if ($sourceType === 'BANK_TRANSACTION' && $this->isProcessingSplitAmountField($fieldText)) {
            return !preg_match('/(^|_)(deposit|withdraw|withdrawal)(_|$)|입금|출금/u', $text);
        }
        return false;
    }

    private function isProcessingSplitAmountField(string $field): bool
    {
        $field = strtolower(trim($field));
        if ($field === '') {
            return false;
        }
        if (in_array($field, ['balance_amount', 'unit_price', 'foreign_unit_price', 'exchange_rate', 'rate'], true)) {
            return false;
        }
        return (bool) preg_match('/(^|_)(amount|vat|tax|fee|charge|duty|deposit|withdraw|withdrawal|supply|total|settlement|gross|withholding)(_|$)/', $field);
    }

    private function processingChildBasePayload(array $parent): array
    {
        return [
            'source_domain' => $parent['source_domain'] ?? null,
            'source_table' => $parent['source_table'] ?? '',
            'source_id' => $parent['source_id'] ?? '',
            'source_type' => $parent['source_type'] ?? '',
            'line_type' => $parent['line_type'] ?? null,
            'transaction_status' => 'NONE',
            'voucher_status' => 'NONE',
            'readiness_status' => $parent['readiness_status'] ?? 'UNKNOWN',
            'correction_status' => 'NONE',
            'item_date' => $parent['item_date'] ?? null,
            'client_id' => $parent['client_id'] ?? null,
            'project_id' => $parent['project_id'] ?? null,
            'employee_id' => $parent['employee_id'] ?? null,
            'bank_account_id' => $parent['bank_account_id'] ?? null,
            'card_id' => $parent['card_id'] ?? null,
            'account_id' => $parent['account_id'] ?? null,
            'currency' => $parent['currency'] ?? 'KRW',
            'raw_json' => $parent['raw_json'] ?? null,
        ];
    }

    private function formatAmountForMessage(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
