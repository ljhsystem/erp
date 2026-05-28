<?php

namespace App\Services\Ledger;

use App\Models\Ledger\ProcessingItemModel;
use Core\Database;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PDO;

class ProcessingItemSplitService
{
    private PDO $db;
    private ProcessingItemModel $itemModel;
    private ProcessingItemActionService $actionService;
    private ProcessingItemAggregateService $aggregateService;

    public function __construct(
        ?PDO $pdo = null,
        ?ProcessingItemModel $itemModel = null,
        ?ProcessingItemActionService $actionService = null,
        ?ProcessingItemAggregateService $aggregateService = null
    ) {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
        $this->itemModel = $itemModel ?? new ProcessingItemModel($this->db);
        $this->actionService = $actionService ?? new ProcessingItemActionService(null, $this->itemModel);
        $this->aggregateService = $aggregateService ?? new ProcessingItemAggregateService($this->db);
    }

    public function split(string $parentItemId, array $children, string $reason = '', string $actor = ''): array
    {
        $parentItemId = trim($parentItemId);
        $actor = $actor !== '' ? $actor : ActorHelper::user();
        if ($parentItemId === '' || count($children) < 2) {
            return ['success' => false, 'message' => 'Split requires a parent item and at least two child items.'];
        }

        $parent = $this->itemModel->getById($parentItemId);
        if (!$parent) {
            return ['success' => false, 'message' => 'Processing item not found.'];
        }
        if ((int) ($parent['is_current'] ?? 1) !== 1 || !in_array((string) ($parent['item_status'] ?? ''), ['ACTIVE', 'NORMAL', 'ERROR'], true)) {
            return ['success' => false, 'message' => 'Only current active processing items can be split.'];
        }

        $validation = $this->validateAmounts($parent, $children);
        if (!$validation['success']) {
            return $validation;
        }

        $groupId = UuidHelper::generate();
        $created = [];

        $this->db->beginTransaction();
        try {
            $beforeStatus = $this->statusSnapshot($parent);
            $beforePayload = $this->payloadSnapshot($parent);
            $this->itemModel->update($parentItemId, [
                'item_status' => 'SPLIT',
                'is_current' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $actor,
            ]);
            $afterParent = $this->itemModel->getById($parentItemId) ?? [];
            $this->actionService->recordAction($parentItemId, 'STATUS_CHANGE', [
                'action_group_id' => $groupId,
                'action_reason' => $reason,
                'action_source' => 'API',
                'actor_user_id' => $actor,
                'before_status_json' => $beforeStatus,
                'after_status_json' => $this->statusSnapshot($afterParent),
                'before_payload_json' => $beforePayload,
                'after_payload_json' => $this->payloadSnapshot($afterParent),
            ]);

            foreach (array_values($children) as $index => $child) {
                $childId = UuidHelper::generate();
                $childSortNo = (int) ($child['sort_no'] ?? ($index + 1));
                $parentDisplayPath = trim((string) ($parent['display_path'] ?? ''));
                if ($parentDisplayPath === '') {
                    $parentDisplayPath = (string) ($parent['sort_no'] ?? 1);
                }

                $payload = array_merge($this->childBasePayload($parent), [
                    'id' => $childId,
                    'parent_item_id' => $parentItemId,
                    'source_item_id' => $parentItemId,
                    'lineage_root_id' => $parent['lineage_root_id'] ?? $parentItemId,
                    'display_path' => $parentDisplayPath . '-' . $childSortNo,
                    'split_group_id' => $groupId,
                    'sort_no' => $childSortNo,
                    'item_type' => $child['item_type'] ?? 'SPLIT',
                    'line_type' => $child['line_type'] ?? ($parent['line_type'] ?? null),
                    'item_status' => 'ACTIVE',
                    'transaction_status' => 'NONE',
                    'voucher_status' => 'NONE',
                    'readiness_status' => $child['readiness_status'] ?? ($parent['readiness_status'] ?? 'UNKNOWN'),
                    'correction_status' => $child['correction_status'] ?? 'NONE',
                    'is_current' => 1,
                    'client_id' => $child['client_id'] ?? ($parent['client_id'] ?? null),
                    'project_id' => $child['project_id'] ?? ($parent['project_id'] ?? null),
                    'employee_id' => $child['employee_id'] ?? ($parent['employee_id'] ?? null),
                    'bank_account_id' => $child['bank_account_id'] ?? ($parent['bank_account_id'] ?? null),
                    'card_id' => $child['card_id'] ?? ($parent['card_id'] ?? null),
                    'account_id' => $child['account_id'] ?? ($parent['account_id'] ?? null),
                    'quantity' => $child['quantity'] ?? null,
                    'unit_price' => $child['unit_price'] ?? null,
                    'supply_amount' => $child['supply_amount'] ?? null,
                    'vat_amount' => $child['vat_amount'] ?? null,
                    'total_amount' => $child['total_amount'] ?? null,
                    'currency' => $child['currency'] ?? ($parent['currency'] ?? 'KRW'),
                    'description' => $child['description'] ?? ($parent['description'] ?? null),
                    'memo' => $child['memo'] ?? null,
                    'mapped_payload_json' => $this->encodeJson($child['mapped_payload'] ?? $child['mapped_payload_json'] ?? null),
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => $actor,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'updated_by' => $actor,
                ]);
                $this->itemModel->insert($payload);
                $newChild = $this->itemModel->getById($childId) ?? $payload;
                $created[] = $newChild;
                $this->actionService->recordAction($childId, 'CREATED', [
                    'action_group_id' => $groupId,
                    'related_processing_item_id' => $parentItemId,
                    'action_reason' => $reason,
                    'action_source' => 'API',
                    'actor_user_id' => $actor,
                    'after_status_json' => $this->statusSnapshot($newChild),
                    'after_payload_json' => $this->payloadSnapshot($newChild),
                ]);
            }

            $this->actionService->recordAction($parentItemId, 'SPLIT', [
                'action_group_id' => $groupId,
                'action_reason' => $reason,
                'action_source' => 'API',
                'actor_user_id' => $actor,
                'before_status_json' => $beforeStatus,
                'after_status_json' => $this->statusSnapshot($afterParent),
                'before_payload_json' => $beforePayload,
                'after_payload_json' => $this->payloadSnapshot($afterParent),
                'metadata_json' => ['child_ids' => array_values(array_map(static fn(array $row): string => (string) ($row['id'] ?? ''), $created))],
            ]);

            if ((string) ($parent['source_table'] ?? '') === 'ledger_data_evidences') {
                $this->aggregateService->syncEvidenceHeaderStatus((string) ($parent['source_id'] ?? ''), $actor);
            }

            $this->db->commit();
            return ['success' => true, 'parent_id' => $parentItemId, 'action_group_id' => $groupId, 'children' => $created];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function validateAmounts(array $parent, array $children): array
    {
        $sourceType = strtoupper((string) ($parent['source_type'] ?? ''));
        $fields = $this->isBankSourceType($sourceType)
            ? ['deposit_amount', 'withdraw_amount']
            : ['quantity', 'supply_amount', 'vat_amount', 'total_amount'];
        foreach ($fields as $field) {
            $parentValue = $this->itemAmount($parent, $field);
            if ($parentValue === null) {
                continue;
            }
            $sum = 0.0;
            foreach ($children as $child) {
                $sum += (float) ($this->itemAmount($child, $field) ?? 0);
            }
            if (abs($parentValue - $sum) > 0.01) {
                return [
                    'success' => false,
                    'message' => sprintf('Split amount mismatch for %s. parent=%s children=%s', $field, $parentValue, $sum),
                    'field' => $field,
                ];
            }
        }

        return ['success' => true];
    }

    private function isBankSourceType(string $sourceType): bool
    {
        $type = strtoupper(trim($sourceType));
        return $type === 'BANK'
            || $type === 'BANK_TRANSACTION'
            || str_contains($type, 'BANK');
    }

    private function itemAmount(array $item, string $field): ?float
    {
        $value = $item[$field] ?? null;
        if ($value === null && isset($item['mapped_payload']) && is_array($item['mapped_payload'])) {
            $value = $item['mapped_payload'][$field] ?? null;
        }
        if ($value === null && isset($item['mapped_payload_json'])) {
            $payload = is_array($item['mapped_payload_json'])
                ? $item['mapped_payload_json']
                : json_decode((string) $item['mapped_payload_json'], true);
            if (is_array($payload)) {
                $value = $payload[$field] ?? null;
            }
        }

        return $this->amount($value);
    }

    private function childBasePayload(array $parent): array
    {
        return [
            'source_domain' => $parent['source_domain'] ?? null,
            'source_table' => $parent['source_table'] ?? '',
            'source_id' => $parent['source_id'] ?? '',
            'source_type' => $parent['source_type'] ?? '',
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

    private function amount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function statusSnapshot(array $item): array
    {
        return [
            'item_status' => $item['item_status'] ?? null,
            'readiness_status' => $item['readiness_status'] ?? null,
            'correction_status' => $item['correction_status'] ?? null,
            'transaction_status' => $item['transaction_status'] ?? null,
            'voucher_status' => $item['voucher_status'] ?? null,
            'is_current' => isset($item['is_current']) ? (bool) (int) $item['is_current'] : null,
        ];
    }

    private function payloadSnapshot(array $item): array
    {
        return [
            'client_id' => $item['client_id'] ?? null,
            'project_id' => $item['project_id'] ?? null,
            'employee_id' => $item['employee_id'] ?? null,
            'account_id' => $item['account_id'] ?? null,
            'quantity' => $item['quantity'] ?? null,
            'unit_price' => $item['unit_price'] ?? null,
            'supply_amount' => $item['supply_amount'] ?? null,
            'vat_amount' => $item['vat_amount'] ?? null,
            'total_amount' => $item['total_amount'] ?? null,
            'currency' => $item['currency'] ?? null,
            'description' => $item['description'] ?? null,
            'memo' => $item['memo'] ?? null,
        ];
    }

    private function encodeJson(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }
}
