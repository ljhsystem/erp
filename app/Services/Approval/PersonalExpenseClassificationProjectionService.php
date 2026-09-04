<?php

namespace App\Services\Approval;

use App\Models\Approval\PersonalExpenseClassificationCorrectionModel;
use Core\Helpers\ActorHelper;
use PDO;

class PersonalExpenseClassificationProjectionService
{
    private PersonalExpenseClassificationCorrectionModel $model;

    public function __construct(PDO $pdo)
    {
        $this->model = new PersonalExpenseClassificationCorrectionModel($pdo);
    }

    public function forDocument(string $documentId): array
    {
        if (trim($documentId) === '') {
            throw new \InvalidArgumentException('개인경비 문서 ID가 필요합니다.');
        }

        return ActorHelper::enrichActorNames(
            $this->model->listEffectiveForDocument($documentId),
            ['classification_corrected_by_name' => 'classification_corrected_by']
        );
    }

    public function forItemIds(array $itemIds): array
    {
        $indexed = [];
        foreach ($this->model->effectiveForItemIds($itemIds) as $row) {
            $indexed[(string) $row['personal_expense_item_id']] = $row;
        }
        return $indexed;
    }
}
