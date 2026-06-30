<?php
namespace App\Services\System;

use PDO;
use App\Models\System\ClientModel;
use App\Services\File\FileService;
use Core\Helpers\UuidHelper;
use Core\Helpers\ActorHelper;
use Core\Helpers\DataHelper;
use Core\Helpers\SequenceHelper;
use Core\Security\Crypto;
use Core\LoggerFactory;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;


class ClientService
{
    private readonly PDO $pdo;
    private ClientModel $model;
    private FileService $fileService;
    private ClientTrashService $trashService;
    private ClientExcelService $excelService;

    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->model  = new ClientModel($this->pdo);
        $this->fileService  = new FileService($this->pdo);
        $this->trashService = new ClientTrashService($this->pdo, $this->model, $this->fileService);
        $this->excelService = new ClientExcelService($this->pdo, $this->model);
        $this->logger = LoggerFactory::getLogger('service-system.ClientService');

        $this->logger->info('ClientService initialized');
    }

    public function getList(array $filters = []): array
    {
        $this->logger->info('getList() called', [
            'filters' => $filters
        ]);

        try {

            $rows = $this->model->getList($filters);

            $this->logger->info('getList() success', [
                'count' => count($rows)
            ]);

            $crypto = new Crypto();

            foreach ($rows as &$row) {
                if (!empty($row['rrn'])) {
                    $rrn = $crypto->decryptResidentNumber($row['rrn']);
                    $row['rrn'] = preg_replace('/\D+/', '', $rrn);
                } else {
                    $row['rrn'] = '';
                }
            }

            unset($row);

            return $rows;

        } catch (\Throwable $e) {

            $this->logger->error('getList() failed', [
                'filters'   => $filters,
                'exception' => $e->getMessage()
            ]);

            return [];
        }
    }

    public function getById(string $id): ?array
    {
        $this->logger->info('getById() called', ['id' => $id]);

        try {

            $row = $this->model->getById($id);

            if (!$row) {
                $this->logger->warning('getById() not found', ['id' => $id]);
                return null;
            }

            $crypto = new Crypto();
            $this->logger->info('rrn raw', [
                'db' => $row['rrn']
            ]);
            if (!empty($row['rrn'])) {
                $rrn = $crypto->decryptResidentNumber($row['rrn']);
                $row['rrn'] = preg_replace('/\D+/', '', $rrn);
            } else {
                $row['rrn'] = '';
            }

            $this->logger->info('rrn decrypted', [
                'value' => $rrn ?? null
            ]);
            $row['company_name_history'] = $this->model->getCompanyNameHistory($id);
            return $row;
        } catch (\Throwable $e) {

            $this->logger->error('getById() exception', [
                'id'        => $id,
                'exception' => $e->getMessage()
            ]);

            return null;
        }
    }

    public function searchPicker(string $keyword, array $options = []): array
    {
        $this->logger->info('searchPicker() called', [
            'keyword' => $keyword,
            'options' => $options,
        ]);

        try {

            $rows = $this->model->searchPicker($keyword, 20, $options);

            if (empty($rows)) {
                return [];
            }

            $results = [];

            foreach ($rows as $row) {

                $text = $row['client_name'] ?? '';

                if (!empty($row['business_number'])) {
                    $text .= ' (' . $row['business_number'] . ')';
                }

                if (!empty($row['company_name']) && $row['company_name'] !== $row['client_name']) {
                    $text .= ' / ' . $row['company_name'];
                }

                $results[] = [
                    'id'                   => $row['id'],
                    'text'                 => $text,
                    'client_name'          => $row['client_name'] ?? '',
                    'company_name'         => $row['company_name'] ?? '',
                    'business_number'      => $row['business_number'] ?? '',
                    'ceo_name'             => $row['ceo_name'] ?? '',
                    'phone'                => $row['phone'] ?? '',
                    'email'                => $row['email'] ?? '',
                    'address'              => $row['address'] ?? '',
                    'address_detail'       => $row['address_detail'] ?? '',
                    'default_account_id'   => $row['default_account_id'] ?? '',
                    'default_account_code' => $row['default_account_code'] ?? '',
                    'default_account_name' => $row['default_account_name'] ?? '',
                    'default_account_text' => $row['default_account_text'] ?? '',
                ];
            }

            return $results;

        } catch (\Throwable $e) {

            $this->logger->error('searchPicker() failed', [
                'keyword'   => $keyword,
                'exception' => $e->getMessage()
            ]);

            return [];
        }
    }

    public function deleteCompanyNameHistory(string $historyId): array
    {
        $this->logger->info('deleteCompanyNameHistory() called', [
            'history_id' => $historyId,
        ]);

        try {
            $deleted = $this->model->deleteCompanyNameHistory($historyId);

            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => '삭제할 상호 변경 이력을 찾을 수 없습니다.',
                ];
            }

            return [
                'success' => true,
                'message' => '상호 변경 이력을 삭제했습니다.',
            ];
        } catch (\Throwable $e) {
            $this->logger->error('deleteCompanyNameHistory() failed', [
                'history_id' => $historyId,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => '삭제 중 오류가 발생했습니다.',
            ];
        }
    }

    public function save(array $data, string $actorType = 'USER', array $files = []): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('save() called', [
            'mode'      => !empty($data['id']) ? 'UPDATE' : 'INSERT',
            'id'        => $data['id'] ?? null,
            'sort_no'      => $data['sort_no'] ?? null,
            'actorType' => $actorType,
            'actor'     => $actor
        ]);

        $newBusinessPath = null;
        $newRrnPath = null;
        $newBankPath = null;

        try {

            $this->pdo->beginTransaction();

            $deleteBusiness = !empty($data['delete_business_certificate']);
            $deleteRrn      = !empty($data['delete_rrn_image']);
            $deleteBank     = !empty($data['delete_bank_file']);


            $data = DataHelper::normalizeClient($data);
            $data = $this->normalizeNullableClientFields($data);

            $id   = trim((string)($data['id'] ?? ''));
            $mode = $id === '' ? 'CREATE' : 'UPDATE';

            $before = [];

            if ($id) {
                $before = $this->model->getById($id) ?? [];

                if (!$before) {
                    throw new \Exception('수정할 거래처 정보를 찾을 수 없습니다.');
                }
            }

            $rrnInput = trim((string)($data['rrn'] ?? ''));

            if ($rrnInput === '') {

                $data['rrn'] = $before['rrn'] ?? null;

            } else {

                if (strpos($rrnInput, '*') !== false) {
                    throw new \Exception('마스킹된 주민등록번호는 저장할 수 없습니다.');
                }

                $rrnRaw = preg_replace('/\D+/', '', $rrnInput);

                if ($rrnRaw !== '') {

                    $crypto = new Crypto();
                    $data['rrn'] = $crypto->encryptResidentNumber($rrnRaw);

                } else {
                    $data['rrn'] = null;
                }
            }

            $id   = trim((string)($data['id'] ?? ''));
            $mode = $id === '' ? 'CREATE' : 'UPDATE';

            $before = [];

            if ($id) {
                $before = $this->model->getById($id) ?? [];

                if (!$before) {
                    throw new \Exception('수정할 거래처 정보를 찾을 수 없습니다.');
                }
            }

            if ($deleteBusiness && empty($files['business_certificate']['tmp_name'])) {
                if (!empty($before['business_certificate'])) {
                    $this->fileService->delete($before['business_certificate']);
                }
                $data['business_certificate'] = null;
            }

            if ($deleteBank && empty($files['bank_file']['tmp_name'])) {
                if (!empty($before['bank_file'])) {
                    $this->fileService->delete($before['bank_file']);
                }
                $data['bank_file'] = null;
            }

            if ($deleteRrn) {

                if (!empty($before['rrn_image'])) {
                    $this->fileService->delete($before['rrn_image']);
                }

                $data['rrn_image'] = null;

            if (
                isset($files['business_certificate']['error']) &&
                $files['business_certificate']['error'] !== UPLOAD_ERR_NO_FILE &&
                $files['business_certificate']['error'] !== UPLOAD_ERR_OK
            ) {
                throw new \Exception($this->resolveUploadErrorMessage(
                    $files['business_certificate']['error'],
                    '사업자등록증'
                ));
            }

            if (
                isset($files['rrn_image']['error']) &&
                $files['rrn_image']['error'] !== UPLOAD_ERR_NO_FILE &&
                $files['rrn_image']['error'] !== UPLOAD_ERR_OK
            ) {
                throw new \Exception($this->resolveUploadErrorMessage(
                    $files['rrn_image']['error'],
                    '주민등록증'
                ));
            }

            if (
                isset($files['bank_file']['error']) &&
                $files['bank_file']['error'] !== UPLOAD_ERR_NO_FILE &&
                $files['bank_file']['error'] !== UPLOAD_ERR_OK
            ) {
                throw new \Exception($this->resolveUploadErrorMessage(
                    $files['bank_file']['error'],
                    '통장사본'
                ));
            }
            }

            if (!empty($files['business_certificate']['tmp_name'])) {

                $oldPath = $before['business_certificate'] ?? null;

                $upload = $this->fileService->uploadBusinessCert(
                    $files['business_certificate']
                );

                if (empty($upload['success'])) {
                    throw new \Exception($upload['message']);
                }

                $data['business_certificate'] = $upload['db_path'];
                $newBusinessPath = $upload['db_path'];

                if (!empty($oldPath)) {
                    $this->fileService->delete($oldPath);
                }
            }

            if (!empty($files['rrn_image']['tmp_name'])) {

                $oldPath = $before['rrn_image'] ?? null;

                $upload = $this->fileService->uploadPrivateIdDoc(
                    $files['rrn_image']
                );

                if (empty($upload['success'])) {
                    throw new \Exception($upload['message']);
                }

                $data['rrn_image'] = $upload['db_path'];
                $newRrnPath = $upload['db_path'];
                if (!empty($oldPath)) {
                    $this->fileService->delete($oldPath);
                }
            }

            if (!empty($files['bank_file']['tmp_name'])) {

                $oldPath = $before['bank_file'] ?? null;

                $upload = $this->fileService->uploadBankCopy(
                    $files['bank_file']
                );

                if (empty($upload['success'])) {
                    throw new \Exception($upload['message']);
                }

                $data['bank_file'] = $upload['db_path'];
                $newBankPath = $upload['db_path'];

                if (!empty($oldPath)) {
                    $this->fileService->delete($oldPath);
                }
            }

            if (
                !array_key_exists('business_certificate', $data)
                && !$deleteBusiness
            ) {
                $data['business_certificate'] =
                    $before['business_certificate'] ?? null;
            }
            if (
                !array_key_exists('rrn_image', $data)
                && !$deleteRrn
            ) {
                $data['rrn_image'] =
                    $before['rrn_image'] ?? null;
            }

            if (
                !array_key_exists('bank_file', $data)
                && !$deleteBank
            ) {
                $data['bank_file'] =
                    $before['bank_file'] ?? null;
            }

            unset($data['delete_business_certificate']);
            unset($data['delete_bank_file']);
            unset($data['delete_rrn_image']);

            if ($id) {
                $data['updated_by'] = $actor;

                $updateData = $data;

                unset($updateData['id']);

                if (empty($updateData)) {

                    $this->pdo->commit();

                    return [
                        'success' => true,
                        'id'      => $id,
                        'sort_no' => $before['sort_no'] ?? null,
                        'message' => '변경사항이 없습니다.'
                    ];
                }

                if (!$this->model->updateById($id, $updateData)) {
                    throw new \Exception('거래처 수정에 실패했습니다.');
                }

                $oldCompanyName = trim((string)($before['company_name'] ?? ''));
                $newCompanyName = trim((string)($updateData['company_name'] ?? ''));
                if ($oldCompanyName !== $newCompanyName) {
                    $this->model->insertCompanyNameHistory($id, $oldCompanyName, $newCompanyName, $actor);
                }

                $this->pdo->commit();

                return [
                    'success' => true,
                    'id'      => $id,
                    'sort_no' => $before['sort_no'] ?? null
                ];
            }

            $newId   = UuidHelper::generate();
            $newSortNo = (int)($data['sort_no'] ?? 0);
            if ($newSortNo <= 0) {
                $newSortNo = SequenceHelper::next('system_clients', 'sort_no');
            }

            $insertData = array_merge($data, [
                'id'         => $newId,
                'sort_no'    => $newSortNo,
                'created_by' => $actor,
                'updated_by' => $actor
            ]);
            if (!$this->model->create($insertData)) {
                throw new \Exception('거래처 등록에 실패했습니다.');
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'id'      => $newId,
                'sort_no'    => $newSortNo
            ];

        } catch (\Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            if (!empty($newBusinessPath)) {
                $this->fileService->delete($newBusinessPath);
            }

            if (!empty($newRrnPath)) {
                $this->fileService->delete($newRrnPath);
            }

            if (!empty($newBankPath)) {
                $this->fileService->delete($newBankPath);
            }

            $this->logger->error('save() failed', [
                'error' => $e->getMessage(),
                'newBusinessPath' => $newBusinessPath,
                'newBankPath' => $newBankPath
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function delete(string $id, string $actorType = 'USER'): array
    {
        return $this->trashService->delete($id, $actorType);

        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('delete() called', [
            'id'        => $id,
            'actorType' => $actorType,
            'actor'     => $actor
        ]);

        try {

            $item = $this->model->getById($id);

            if (!$item) {
                $this->logger->warning('delete() not found', ['id' => $id]);
                return [
                    'success' => false,
                    'message' => '거래처 정보를 찾을 수 없습니다.'
                ];
            }

            if (!$this->model->deleteById($id, $actor)) {

                $this->logger->error('delete() DB failed', [
                    'id'   => $id,
                    'user' => $actor
                ]);

                return [
                    'success' => false,
                    'message' => '거래처 삭제에 실패했습니다.'
                ];
            }

            $this->logger->info('delete() success', ['id' => $id]);

            return ['success' => true];
        } catch (\Throwable $e) {

            $this->logger->error('delete() exception', [
                'id'        => $id,
                'exception' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function getTrashList(): array
    {
        return $this->trashService->getTrashList();

        $this->logger->info('getTrashList() called');

        try {

            return $this->model->getDeleted();

        } catch (\Throwable $e) {

            $this->logger->error('getTrashList() exception', [
                'exception' => $e->getMessage()
            ]);

            return [];
        }
    }

    public function restore(string $id, string $actorType = 'USER'): array
    {
        return $this->trashService->restore($id, $actorType);

        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('restore() called', [
            'id'        => $id,
            'actorType' => $actorType,
            'actor'     => $actor
        ]);

        $client = $this->model->getById($id);

        if (!$client) {
            return [
                'success' => false,
                'message' => '거래처 정보를 찾을 수 없습니다.'
            ];
        }

        $ok = $this->model->restoreById($id, $actor);

        return [
            'success' => $ok
        ];
    }

    public function restoreBulk(array $ids, string $actorType = 'USER'): array
    {
        return $this->trashService->restoreBulk($ids, $actorType);

        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('restoreBulk() called', [
            'ids' => $ids,
            'actor' => $actor
        ]);

        if (empty($ids)) {
            return ['success' => false, 'message' => 'ID가 올바르지 않습니다.'];
        }

        $this->pdo->beginTransaction();

        try {

            $success = 0;

            foreach ($ids as $id) {

                $ok = $this->model->restoreById($id, $actor);

                if ($ok) $success++;
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "복원 완료 ({$success}건)"
            ];

        } catch (\Throwable $e) {

            $this->pdo->rollBack();

            $this->logger->error('restoreBulk() failed', [
                'exception' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function restoreAll(string $actorType = 'USER'): array
    {
        return $this->trashService->restoreAll($actorType);

        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('restoreAll() called', [
            'actor' => $actor
        ]);

        $this->pdo->beginTransaction();

        try {

            $rows = $this->model->getDeleted();

            $success = 0;

            foreach ($rows as $row) {

                $ok = $this->model->restoreById($row['id'], $actor);

                if ($ok) $success++;
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "전체 복원 완료 ({$success}건)"
            ];

        } catch (\Throwable $e) {

            $this->pdo->rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function purge(string $id, string $actorType = 'USER'): array
    {
        return $this->trashService->purge($id, $actorType);

        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('purge() called', [
            'id'        => $id,
            'actorType' => $actorType,
            'actor'     => $actor
        ]);

        $client = $this->model->getById($id);

        if (!$client) {
            return [
                'success' => false,
                'message' => '거래처 정보를 찾을 수 없습니다.'
            ];
        }

        $this->pdo->beginTransaction();

        try {

            if (!empty($client['business_certificate'])) {

                $this->fileService->delete($client['business_certificate']);

                $this->logger->info('business_certificate deleted', [
                    'path' => $client['business_certificate']
                ]);
            }
            if (!empty($client['rrn_image'])) {

                $this->fileService->delete($client['rrn_image']);

                $this->logger->info('rrn_image deleted', [
                    'path' => $client['rrn_image']
                ]);
            }
            if (!empty($client['bank_file'])) {

                $this->fileService->delete($client['bank_file']);

                $this->logger->info('bank_file deleted', [
                    'path' => $client['bank_file']
                ]);
            }

            $ok = $this->model->hardDeleteById($id);

            if (!$ok) {
                throw new \Exception('DB 삭제에 실패했습니다.');
            }

            $this->pdo->commit();

            return [
                'success' => true
            ];

        } catch (\Throwable $e) {

            $this->pdo->rollBack();

            $this->logger->error('purge() failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '완전 삭제에 실패했습니다.'
            ];
        }
    }

    public function purgeBulk(array $ids, string $actorType = 'USER'): array
    {
        return $this->trashService->purgeBulk($ids, $actorType);

        $actor = ActorHelper::resolve($actorType);

        if (empty($ids)) {
            return ['success' => false, 'message' => 'ID가 올바르지 않습니다.'];
        }

        $this->pdo->beginTransaction();

        try {

            $success = 0;

            foreach ($ids as $id) {

                $client = $this->model->getById($id);

                if (!$client) {
                    continue;
                }

                if (!empty($client['business_certificate'])) {

                    $this->fileService->delete($client['business_certificate']);

                    $this->logger->info('business_certificate deleted', [
                        'id'   => $id,
                        'path' => $client['business_certificate']
                    ]);
                }

                if (!empty($client['rrn_image'])) {

                    $this->fileService->delete($client['rrn_image']);

                    $this->logger->info('rrn_image deleted', [
                        'id'   => $id,
                        'path' => $client['rrn_image']
                    ]);
                }

                if (!empty($client['bank_file'])) {

                    $this->fileService->delete($client['bank_file']);

                    $this->logger->info('bank_file deleted', [
                        'id'   => $id,
                        'path' => $client['bank_file']
                    ]);
                }

                $ok = $this->model->hardDeleteById($id);

                if ($ok) $success++;
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "완전 삭제 완료 ({$success}건)"
            ];

        } catch (\Throwable $e) {

            $this->pdo->rollBack();

            $this->logger->error('purgeBulk() failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function purgeAll(string $actorType = 'USER'): array
    {
        return $this->trashService->purgeAll($actorType);

        $actor = ActorHelper::resolve($actorType);

        $this->pdo->beginTransaction();

        try {

            $rows = $this->model->getDeleted();

            $success = 0;

            foreach ($rows as $row) {

                if (!empty($row['business_certificate'])) {

                    $this->fileService->delete($row['business_certificate']);

                    $this->logger->info('business_certificate deleted', [
                        'id'   => $row['id'],
                        'path' => $row['business_certificate']
                    ]);
                }
                if (!empty($row['rrn_image'])) {

                    $this->fileService->delete($row['rrn_image']);

                    $this->logger->info('rrn_image deleted', [
                        'id'   => $row['id'],
                        'path' => $row['rrn_image']
                    ]);
                }
                if (!empty($row['bank_file'])) {

                    $this->fileService->delete($row['bank_file']);

                    $this->logger->info('bank_file deleted', [
                        'id'   => $row['id'],
                        'path' => $row['bank_file']
                    ]);
                }

                $ok = $this->model->hardDeleteById($row['id']);

                if ($ok) $success++;
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "전체 완전 삭제 완료 ({$success}건)"
            ];

        } catch (\Throwable $e) {

            $this->pdo->rollBack();

            $this->logger->error('purgeAll() failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function reorder(array $changes): bool
    {
        return $this->trashService->reorder($changes);

        $this->logger->info('reorder() called', [
            'changes' => $changes
        ]);

        if (empty($changes)) {
            return true;
        }

        try {

            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
            }

            foreach ($changes as $row) {

                if (
                    empty($row['id']) ||
                    !isset($row['newSortNo'])
                ) {
                    throw new \Exception('reorder 데이터 오류');
                }
            }

            foreach ($changes as $row) {

                $tempSortNo = (int)$row['newSortNo'] + 1000000;

                $this->model->updateSortNo(
                    $row['id'],
                    $tempSortNo
                );
            }

            foreach ($changes as $row) {

                $this->model->updateSortNo(
                    $row['id'],
                    (int)$row['newSortNo']
                );
            }

            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            $this->logger->info('reorder() success');

            return true;

        } catch (\Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('reorder() failed', [
                'exception' => $e->getMessage(),
                'changes' => $changes
            ]);

            throw $e;
        }
    }

    public function downloadTemplate(?string $columnsCsv = null): void
    {
        $this->excelService->downloadTemplate($columnsCsv);
        return;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('거래처 업로드');
        $headers = ['거래처명', '상호명', '대표자명', '사업자등록번호', '업태', '전화', '이메일', '등록일자', '비고'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([['샘플 거래처', '샘플 상호', '홍길동', '123-45-67890', '서비스업', '02-1234-5678', 'sample@example.com', date('Y-m-d'), '']], null, 'A2');
        foreach (range('A', 'I') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="client_template.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        exit;
    }

    public function saveFromExcelFile(string $filePath): array
    {
        return $this->excelService->saveFromExcelFile(
            $filePath,
            fn(array $payload, string $actorType = 'SYSTEM'): array => $this->save($payload, $actorType)
        );

        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, false, false, false);
        if (empty($rows) || count($rows) < 2) { return ['success' => false, 'message' => '업로드할 데이터가 없습니다.']; }
        $header = array_map(fn($v) => trim((string)$v), array_shift($rows));
        $map = array_flip($header);
        $count = 0;
        foreach ($rows as $row) {
            if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) { continue; }
            $payload = [
                'client_name' => trim((string)($row[$map['거래처명'] ?? -1] ?? '')),
                'company_name' => trim((string)($row[$map['상호명'] ?? -1] ?? '')),
                'ceo_name' => trim((string)($row[$map['대표자명'] ?? -1] ?? '')),
                'business_number' => trim((string)($row[$map['사업자등록번호'] ?? -1] ?? '')),
                'business_status' => trim((string)($row[$map['업태'] ?? -1] ?? '')),
                'phone' => trim((string)($row[$map['전화'] ?? -1] ?? '')),
                'email' => trim((string)($row[$map['이메일'] ?? -1] ?? '')),
                'registration_date' => trim((string)($row[$map['등록일자'] ?? -1] ?? '')) ?: date('Y-m-d'),
                'note' => trim((string)($row[$map['비고'] ?? -1] ?? '')),
            ];
            if ($payload['client_name'] === '') { continue; }
            $result = $this->save($payload, 'SYSTEM');
            if (!empty($result['success'])) { $count++; }
        }
        return ['success' => true, 'message' => "{$count}건 업로드되었습니다."];
    }

    public function downloadExcel(?string $columnsCsv = null): void
    {
        $this->excelService->downloadExcel($columnsCsv);
        return;

        $clients = $this->model->getList();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('거래처 목록');
        $sheet->fromArray(['순번', '거래처명', '사업자번호', '대표자명', '전화', '이메일', '주소', '메모'], null, 'A1');
        $rowNo = 2;
        foreach ($clients as $client) {
            $sheet->fromArray([[$client['sort_no'] ?? '', $client['client_name'] ?? '', $client['business_number'] ?? '', $client['ceo_name'] ?? '', $client['phone'] ?? '', $client['email'] ?? '', $client['address'] ?? '', $client['memo'] ?? '']], null, 'A' . $rowNo);
            $rowNo++;
        }
        foreach (range('A', 'H') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="client_list.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        exit;
    }

    public function downloadMigrationTemplate(?string $columnsCsv = null): void { $this->excelService->downloadMigrationTemplate($columnsCsv); }

    public function saveFromMigrationExcelFile(string $filePath, ?string $columnsCsv = null): array { return $this->excelService->saveFromMigrationExcelFile($filePath, fn(array $payload, string $actorType = 'SYSTEM'): array => $this->save($payload, $actorType), $columnsCsv); }
    
    public function downloadMigrationExcel(?string $columnsCsv = null): void { $this->excelService->downloadMigrationExcel($columnsCsv); }

    private function getClientMigrationHeaders(): array
    {
        return $this->excelService->getClientMigrationHeaders();

        return ['거래처명', '상호명', '등록일자', '사업자등록번호', '업태', '대표자명', '전화', '이메일', '주소', '메모'];
    }

    private function getClientMigrationHeaderMap(): array
    {
        return $this->excelService->getClientMigrationHeaderMap();

        return [
            '거래처명' => 'client_name',
            '상호명' => 'company_name',
            '등록일자' => 'registration_date',
            '사업자등록번호' => 'business_number',
            '업태' => 'business_status',
            '대표자명' => 'ceo_name',
            '전화' => 'phone',
            '이메일' => 'email',
            '주소' => 'address',
            '메모' => 'memo',
            'clientname' => 'client_name',
            'companyname' => 'company_name',
            'registrationdate' => 'registration_date',
            'businessnumber' => 'business_number',
            'businessstatus' => 'business_status',
            'ceoname' => 'ceo_name',
            'phone' => 'phone',
            'email' => 'email',
            'address' => 'address',
            'memo' => 'memo',
        ];
    }

    private function normalizeMigrationExcelDate(mixed $value): ?string
    {
        return $this->excelService->normalizeMigrationExcelDate($value);

        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? $value : date('Y-m-d', $timestamp);
    }

    private function parseMigrationExcelActiveValue(mixed $value): int
    {
        return $this->excelService->parseMigrationExcelActiveValue($value);

        $normalized = mb_strtolower(trim((string)$value), 'UTF-8');
        return in_array($normalized, ['1', 'true', 'yes', 'use', 'active', 'y', '사용'], true) ? 1 : 0;
    }

    private function normalizeNullableClientFields(array $data): array
    {
        $nullableFields = [
            'business_number',
            'rrn',
            'company_name',
            'registration_date',
            'business_type',
            'business_category',
            'business_status',
            'ceo_name',
            'ceo_phone',
            'manager_name',
            'manager_phone',
            'phone',
            'fax',
            'email',
            'homepage',
            'address',
            'address_detail',
            'bank_name',
            'account_number',
            'account_holder',
            'trade_category',
            'default_account_id',
            'item_category',
            'client_category',
            'client_type',
            'tax_type',
            'payment_term',
            'client_grade',
            'note',
            'memo',
        ];

        foreach ($nullableFields as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === null) {
                continue;
            }

            $value = trim((string)$data[$field]);
            $data[$field] = $value === '' ? null : $value;
        }

        return $data;
    }



    private function resolveUploadErrorMessage(int $errorCode, string $label): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "{$label} 파일 크기가 업로드 허용 용량을 초과했습니다.",
            UPLOAD_ERR_PARTIAL => "{$label} 파일이 일부만 업로드되었습니다. 다시 시도해주세요.",
            UPLOAD_ERR_NO_TMP_DIR => "{$label} 업로드 임시 폴더를 찾을 수 없습니다.",
            UPLOAD_ERR_CANT_WRITE => "{$label} 파일을 서버에 저장할 수 없습니다.",
            UPLOAD_ERR_EXTENSION => "{$label} 파일 업로드가 서버 확장 모듈에 의해 중단되었습니다.",
            default => "{$label} 업로드 중 오류가 발생했습니다.",
        };
    }
}
