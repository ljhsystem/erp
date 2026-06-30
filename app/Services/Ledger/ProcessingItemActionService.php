<?php

namespace App\Services\Ledger;

use App\Models\Ledger\ProcessingItemActionModel;
use App\Models\Ledger\ProcessingItemModel;
use Core\Helpers\ActorHelper;

class ProcessingItemActionService
{
    private ProcessingItemActionModel $actionModel;
    private ProcessingItemModel $itemModel;

    public function __construct(
        ?ProcessingItemActionModel $actionModel = null,
        ?ProcessingItemModel $itemModel = null
    ) {
        $this->actionModel = $actionModel ?? new ProcessingItemActionModel();
        $this->itemModel = $itemModel ?? new ProcessingItemModel();
    }

    public function recordAction(string $processingItemId, string $actionType, array $context = []): ?array
    {
        $processingItemId = trim($processingItemId);
        $actionType = strtoupper(trim($actionType));
        if ($processingItemId === '' || $actionType === '') {
            return null;
        }

        $item = $this->itemModel->getById($processingItemId) ?? [];

        return $this->actionModel->createAction([
            'processing_item_id' => $processingItemId,
            'action_type' => $actionType,
            'action_group_id' => $context['action_group_id'] ?? null,
            'related_processing_item_id' => $context['related_processing_item_id'] ?? null,
            'related_transaction_id' => $context['related_transaction_id'] ?? null,
            'related_voucher_id' => $context['related_voucher_id'] ?? null,
            'source_domain' => $context['source_domain'] ?? $this->sourceDomainForItem($item),
            'source_table' => $context['source_table'] ?? ($item['source_table'] ?? null),
            'source_type' => $context['source_type'] ?? ($item['source_type'] ?? null),
            'source_id' => $context['source_id'] ?? ($item['source_id'] ?? null),
            'before_status_json' => $context['before_status_json'] ?? null,
            'after_status_json' => $context['after_status_json'] ?? $this->statusSnapshot($item),
            'before_payload_json' => $context['before_payload_json'] ?? null,
            'after_payload_json' => $context['after_payload_json'] ?? null,
            'action_reason' => $context['action_reason'] ?? null,
            'action_source' => $context['action_source'] ?? 'API',
            'actor_type' => $context['actor_type'] ?? 'USER',
            'actor_user_id' => $context['actor_user_id'] ?? ActorHelper::user(),
            'error_message' => $context['error_message'] ?? null,
            'metadata_json' => $context['metadata_json'] ?? null,
        ]);
    }

    private function statusSnapshot(array $item): ?array
    {
        if ($item === []) {
            return null;
        }

        return [
            'item_status' => $item['item_status'] ?? null,
            'readiness_status' => $item['readiness_status'] ?? null,
            'correction_status' => $item['correction_status'] ?? null,
            'transaction_status' => $item['transaction_status'] ?? null,
            'voucher_status' => $item['voucher_status'] ?? null,
        ];
    }

    private function sourceDomainForItem(array $item): ?string
    {
        $sourceTable = (string) ($item['source_table'] ?? '');
        return match ($sourceTable) {
            'ledger_data_evidences' => 'EVIDENCE',
            default => null,
        };
    }
}
