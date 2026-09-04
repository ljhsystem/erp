<?php

namespace App\Services\System;

use App\Models\System\CompanyModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;
use Psr\Log\LoggerInterface;

class CompanyService
{
    private readonly PDO $pdo;
    private CompanyModel $model;
    private LoggerInterface $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->model = new CompanyModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-system-company');
    }

    public function get(): array
    {
        return $this->model->getOne() ?? [];
    }

    public function save(array $data): array
    {
        $this->pdo->beginTransaction();

        try {
            $actor = ActorHelper::user();
            $count = $this->model->countAll();
            if ($count > 1) {
                throw new \Exception('회사정보가 중복 저장되어 있습니다. 데이터 정리 후 다시 저장해주세요.');
            }

            $exists = $this->model->getOne();

            if ($exists) {
                $data['updated_by'] = $actor;

                if (!$this->model->updateById($exists['id'], $data)) {
                    throw new \Exception('회사정보 수정에 실패했습니다.');
                }

                $message = '회사정보를 수정했습니다.';
            } else {
                $data['id'] = UuidHelper::generate();
                $data['created_by'] = $actor;

                if (!$this->model->create($data)) {
                    throw new \Exception('회사정보 등록에 실패했습니다.');
                }

                $message = '회사정보를 등록했습니다.';
            }

            $this->pdo->commit();

            $this->logger->info('회사정보가 저장되었습니다.', [
                'event_code'=>'COMPANY_SAVED','result'=>'SUCCESS','service'=>self::class,
                'action'=>$exists?'update':'create','actor'=>$actor,'target_id'=>$exists['id']??$data['id'],
            ]);

            return [
                'success' => true,
                'message' => $message,
            ];
        } catch (\Throwable $e) {
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            $this->logger->error('회사정보 저장에 실패했습니다.', [
                'event_code'=>'COMPANY_SAVE_FAILED','result'=>'FAILED','service'=>self::class,
                'action'=>'save','actor'=>ActorHelper::user(),'error_code'=>get_class($e),'error'=>$e,
            ]);

            return [
                'success' => false,
                'message' => '회사정보 저장 중 오류가 발생했습니다.',
            ];
        }
    }
}
