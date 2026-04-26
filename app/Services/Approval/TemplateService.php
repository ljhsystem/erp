<?php
// 경로: PROJECT_ROOT/app/Services/Approval/TemplateService.php
namespace App\Services\Approval;

use PDO;
use App\Models\User\ApprovalTemplateModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\Helpers\SequenceHelper;
use Core\LoggerFactory;

class TemplateService
{
    private readonly PDO $pdo;
    private  $model;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo    = $pdo;
        $this->model  = new ApprovalTemplateModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-approval.ApprovalTemplateService');
    }

    /* ------------------------------------------------------------
     * 🔥 Normalize Helper
     * ------------------------------------------------------------ */
    private function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    /* ------------------------------------------------------------
     * 템플릿 키 자동 생성
     * ------------------------------------------------------------ */
    private function generateTemplateKey(string $name): string
    {
        $roman = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name)
              ?: preg_replace('/[^\x20-\x7E]/', '', $name);

        $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $roman), '_'))
             ?: 'template_' . substr(md5(uniqid()), 0, 6);

        $key = $base;
        $i = 1;

        while ($this->model->templateKeyExists($key)) {
            $key = $base . '_' . ($i++);
        }

        return $key;
    }

    /* ------------------------------------------------------------
     * 전체 조회
     * ------------------------------------------------------------ */
    public function getAll(): array
    {
        return $this->model->getAll();
    }

    /* ------------------------------------------------------------
     * 단건 조회
     * ------------------------------------------------------------ */
    public function getById(string $id): ?array
    {
        return $this->model->getById($id);
    }

    /* ------------------------------------------------------------
     * 🔥 템플릿 생성 (중복검사 강화)
     * ------------------------------------------------------------ */
    public function create(array $data): array
    {
        // Normalize
        $data['template_name'] = $this->normalize($data['template_name']);
        $data['document_type'] = $this->normalize($data['document_type']);
        $this->logger->info('[Template Create] 입력', $data);

        // 중복 검사
        if ($this->model->existsName($data['template_name'], $data['document_type'])) {

            $this->logger->warning('[Template Create] 중복 발견', [
                'template_name' => $data['template_name'],
                'document_type' => $data['document_type']
            ]);

            return [
                'success' => false,
                'message' => '이미 동일한 템플릿이 존재합니다.'
            ];
        }

        // 생성
        $id  = UuidHelper::generate();
        $key = $this->generateTemplateKey($data['template_name']);
        $data['sort_no'] = SequenceHelper::next('user_approval_templates', 'sort_no');
        $data['created_by'] = ActorHelper::user();
        $data['updated_by'] = $data['created_by'];

        $this->logger->info('[Template Create] 생성 진행', [
            'id'  => $id,
            'key' => $key
        ]);

        $ok = $this->model->create($id, $key, $data);

        return [
            'success' => (bool)$ok,
            'id'      => $id,
            'key'     => $key
        ];
    }

    /* ------------------------------------------------------------
     * 🔥 템플릿 수정 (중복 검사 강화 + 로그 유지)
     * ------------------------------------------------------------ */
    public function update(string $id, array $data): array
    {
        // Normalize
        $data['template_name'] = $this->normalize($data['template_name']);
        $data['document_type'] = $this->normalize($data['document_type']);
        $data['updated_by'] = ActorHelper::user();

        $this->logger->info('[Template Update] 요청', [
            'id'            => $id,
            'template_name' => $data['template_name'],
            'document_type' => $data['document_type']
        ]);

        // 자기 자신 제외 중복 검사
        if ($this->model->existsName($data['template_name'], $data['document_type'], $id)) {

            $this->logger->warning('[Template Update] 중복 발견', [
                'id'            => $id,
                'template_name' => $data['template_name'],
                'document_type' => $data['document_type']
            ]);

            return [
                'success' => false,
                'message' => '이미 동일한 템플릿이 존재합니다.'
            ];
        }

        $ok = $this->model->update($id, $data);

        $this->logger->info('[Template Update] 완료', [
            'id'      => $id,
            'success' => $ok
        ]);

        return ['success' => $ok];
    }

    /* ------------------------------------------------------------
     * 템플릿 삭제
     * ------------------------------------------------------------ */
    public function delete(string $id): bool
    {
        $this->logger->info('[Template Delete] 요청', ['id' => $id]);
        return $this->model->delete($id);
    }

    public function reorder(array $changes): bool
    {
        if (!$changes) {
            return true;
        }

        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
            }

            foreach ($changes as &$row) {
                $sortNo = $row['newSortNo'] ?? $row['sort_no'] ?? null;
                if (empty($row['id']) || $sortNo === null) {
                    throw new \InvalidArgumentException('순번 변경 데이터가 올바르지 않습니다.');
                }
                $row['_sort_no'] = (int)$sortNo;
            }
            unset($row);

            $actor = ActorHelper::user();

            foreach ($changes as $row) {
                $this->model->updateSortNo($row['id'], $row['_sort_no'] + 1000000, $actor);
            }

            foreach ($changes as $row) {
                $this->model->updateSortNo($row['id'], $row['_sort_no'], $actor);
            }

            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }
}
