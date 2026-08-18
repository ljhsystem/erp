<?php

namespace App\Services\Ledger;

use App\Models\Ledger\ChartAccountReferenceModel;
use PDO;

class ChartAccountReferenceGuardService
{
    private ChartAccountReferenceModel $model;

    public function __construct(PDO $pdo)
    {
        $this->model = new ChartAccountReferenceModel($pdo);
    }

    public function validatePurge(array $requestedIds = []): array
    {
        $requestedIds = array_values(array_unique(array_filter(array_map('strval', $requestedIds))));
        $targetIds = $this->model->deletedIds($requestedIds);

        if ($targetIds === []) {
            return ['success' => false, 'message' => '영구삭제할 휴지통 계정이 없습니다.', 'ids' => []];
        }
        if ($requestedIds !== [] && count($targetIds) !== count($requestedIds)) {
            return ['success' => false, 'message' => '휴지통에 없는 계정이 포함되어 있습니다.', 'ids' => []];
        }
        if ($this->model->blockingChildren($targetIds) > 0) {
            return ['success' => false, 'message' => '삭제 대상에 포함되지 않은 하위 계정이 있어 영구삭제할 수 없습니다.', 'ids' => []];
        }

        $references = $this->model->referenceCounts($targetIds);
        if ($references !== []) {
            $summary = implode(', ', array_map(
                static fn (array $row): string => $row['label'] . ' ' . $row['count'] . '건',
                $references
            ));
            return ['success' => false, 'message' => '사용 중인 계정은 영구삭제할 수 없습니다. (' . $summary . ')', 'ids' => []];
        }

        return ['success' => true, 'ids' => $targetIds, 'references' => []];
    }

    public function referencesFor(string $id): array
    {
        return $id === '' ? [] : $this->model->referenceCounts([$id]);
    }
}
