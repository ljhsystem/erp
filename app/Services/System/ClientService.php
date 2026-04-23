<?php
// 野껋럥以? PROJECT_ROOT . '/app/Services/System/ClientService.php'
// ??살구:
//  - 椰꾧퀡?믭㎗?Client) ?온????뺥돩??
//  - UUID / sort_no ??밴쉐?? Service 筌?굞??
//  - DB 筌ｌ꼶?? ClientModel
//  - 筌뤴뫀諭?雅뚯눘???癒?カ LoggerFactory ?怨몄뒠
namespace App\Services\System;

use PDO;
use App\Models\System\ClientModel;
use App\Services\File\FileService;
use Core\Helpers\UuidHelper;
use Core\Helpers\ActorHelper;
use Core\Helpers\DataHelper;
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

    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->model  = new ClientModel($this->pdo);
        $this->fileService  = new FileService($this->pdo);
        $this->logger = LoggerFactory::getLogger('service-system.ClientService');

        $this->logger->info('ClientService initialized');
    }


    /* ============================================================
     * ?袁⑷퍥 筌뤴뫖以?鈺곌퀬??
     * ============================================================ */
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

    /* ============================================================
     * ??ｊ탷 鈺곌퀬??(id 疫꿸퀣?)
     * ============================================================ */
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
            return $row;
        } catch (\Throwable $e) {

            $this->logger->error('getById() exception', [
                'id'        => $id,
                'exception' => $e->getMessage()
            ]);

            return null;
        }
    }



    /* =========================================================
    * 椰꾧퀡?믭㎗?野꺜??(Service - Select2 ????癰궰??
    * ========================================================= */
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

                // ?逾???毓?癒?쓰???븐늿?졿묾?
                if (!empty($row['business_number'])) {
                    $text .= ' (' . $row['business_number'] . ')';
                }

                // ?逾????텢筌???됱몵筌??곕떽?
                if (!empty($row['company_name']) && $row['company_name'] !== $row['client_name']) {
                    $text .= ' / ' . $row['company_name'];
                }

                $results[] = [
                    'id'   => $row['id'],
                    'text' => $text
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

    /* ============================================================
    * ????(??밴쉐 + ??륁젟)
    * ============================================================ */
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

            /* ?逾?normalize ?袁⑸퓠 ???????삋域밸챶? ?믪눘? ?⑥쥙??*/
            $deleteBusiness = !empty($data['delete_business_certificate']);
            $deleteRrn      = !empty($data['delete_rrn_image']);
            $deleteBank     = !empty($data['delete_bank_file']);


            $data = DataHelper::normalizeClient($data);
            $data = $this->normalizeNullableClientFields($data);

            /* =========================================================
            * ?逾?疫꿸퀣???怨쀬뵠???믪눘? 鈺곌퀬??(餓λ쵐??
            * ========================================================= */
            $id   = trim((string)($data['id'] ?? ''));
            $mode = $id === '' ? 'CREATE' : 'UPDATE';

            $before = [];

            if ($id) {
                $before = $this->model->getById($id) ?? [];

                if (!$before) {
                    throw new \Exception('鈺곕똻???? ??낅뮉 椰꾧퀡?믭㎗?륁뿯??덈뼄.');
                }
            }

            /* =========================================================
            * ?逾?rrn 筌ｌ꼶??(??由경에???猷?
            * ========================================================= */
            $rrnInput = trim((string)($data['rrn'] ?? ''));

            if ($rrnInput === '') {

                $data['rrn'] = $before['rrn'] ?? null;

            } else {

                if (strpos($rrnInput, '*') !== false) {
                    throw new \Exception('筌띾뜆??諛몃쭆 雅뚯눖?甕곕뜇??????館釉?????곷뮸??덈뼄.');
                }

                $rrnRaw = preg_replace('/\D+/', '', $rrnInput);

                if ($rrnRaw !== '') {

                    $crypto = new Crypto();
                    $data['rrn'] = $crypto->encryptResidentNumber($rrnRaw);

                } else {
                    $data['rrn'] = null;
                }
            }

            /* =========================================================
            * ?逾?ID / 筌뤴뫀諭?野껉퀣??
            * ========================================================= */
            $id   = trim((string)($data['id'] ?? ''));
            $mode = $id === '' ? 'CREATE' : 'UPDATE';

            /* =========================================================
            * ?逾?疫꿸퀣???怨쀬뵠???믪눘? 鈺곌퀬??(餓λ쵐??
            * ========================================================= */
            $before = [];

            if ($id) {
                $before = $this->model->getById($id) ?? [];

                if (!$before) {
                    throw new \Exception('鈺곕똻???? ??낅뮉 椰꾧퀡?믭㎗?륁뿯??덈뼄.');
                }
            }


            /* =========================================================
            * ???뵬 筌ｌ꼶??
            * ========================================================= */

            // ?逾??????遺욧퍕
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
            }

            // ?逾????뵬??몄쎗筌ｋ똾寃?
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


            // ?逾???낆쨮??
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

            // ?逾?rrn_image ??낆쨮??筌ｌ꼶??
            if (!empty($files['rrn_image']['tmp_name'])) {

                $oldPath = $before['rrn_image'] ?? null;

                $upload = $this->fileService->uploadPrivateIdDoc(
                    $files['rrn_image']
                );

                if (empty($upload['success'])) {
                    throw new \Exception($upload['message']);
                }

                $data['rrn_image'] = $upload['db_path'];
                $newRrnPath = $upload['db_path'];   // ?逾???由???節뚮선??筌띿쉶??

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

            // ?逾?疫꿸퀣?????뵬 ?醫?
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

            /* =========================================================
            * ?逾????????삋域???볤탢 (DB 癰귣똾??
            * ========================================================= */
            unset($data['delete_business_certificate']);
            unset($data['delete_bank_file']);
            unset($data['delete_rrn_image']);
            /* =========================================================
            * UPDATE
            * ========================================================= */
            if ($id) {

                $data['updated_by'] = $actor;

                $updateData = $data;

                unset($updateData['id']);

                if (empty($updateData)) {

                    $this->pdo->commit();

                    return [
                        'success' => true,
                        'id'      => $id,
                        'sort_no'    => $before['sort_no'] ?? null,
                        'message' => '癰궰野껋럩沅????곸벉'
                    ];
                }

                if (!$this->model->updateById($id, $updateData)) {
                    throw new \Exception('椰꾧퀡?믭㎗???륁젟 ??쎈솭');
                }

                $this->pdo->commit();

                return [
                    'success' => true,
                    'id'      => $id,
                    'sort_no'    => $before['sort_no'] ?? null
                ];
            }

            /* =========================================================
            * INSERT
            * ========================================================= */
            $newId   = UuidHelper::generate();
            $newSortNo = null;

            $insertData = array_merge($data, [
                'id'         => $newId,
                'sort_no'       => $newSortNo,
                'created_by' => $actor,
                'updated_by' => $actor
            ]);

            if (!$this->model->create($insertData)) {
                throw new \Exception('椰꾧퀡?믭㎗??源낆쨯 ??쎈솭');
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

            // ?逾???낆쨮??뺤춸 ??랁?DB 獄쏆꼷????쎈솭?????뵬 ?類ｂ봺
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



    /* ============================================================
     * ????
     * ============================================================ */
    public function delete(string $id, string $actorType = 'USER'): array
    {
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
                    'message' => '鈺곕똻???? ??낅뮉 椰꾧퀡?믭㎗?륁뿯??덈뼄.'
                ];
            }

            if (!$this->model->deleteById($id, $actor)) {

                $this->logger->error('delete() DB failed', [
                    'id'   => $id,
                    'user' => $actor
                ]);

                return [
                    'success' => false,
                    'message' => '椰꾧퀡?믭㎗???????쎈솭'
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

    /* =========================================================
    * ?????筌뤴뫖以?
    * ========================================================= */
    public function getTrashList(): array
    {
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



    /* =========================================================
    癰귣벊??
    ========================================================= */

    public function restore(string $id, string $actorType = 'USER'): array
    {
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
                'message' => '鈺곕똻???? ??낅뮉 椰꾧퀡?믭㎗?륁뿯??덈뼄.'
            ];
        }

        $ok = $this->model->restoreById($id, $actor);

        return [
            'success' => $ok
        ];
    }






    /* =========================================================
    * ?醫뤾문 癰귣벊??
    * ========================================================= */
    public function restoreBulk(array $ids, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('restoreBulk() called', [
            'ids' => $ids,
            'actor' => $actor
        ]);

        if (empty($ids)) {
            return ['success' => false, 'message' => 'ID ??곸벉'];
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
                'message' => "癰귣벊???袁⑥┷ ({$success}椰?"
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



    /* =========================================================
    * ?袁⑷퍥 癰귣벊??
    * ========================================================= */
    public function restoreAll(string $actorType = 'USER'): array
    {
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
                'message' => "?袁⑷퍥 癰귣벊???袁⑥┷ ({$success}椰?"
            ];

        } catch (\Throwable $e) {

            $this->pdo->rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }



    /* =========================================================
    * ?袁⑹읈????
    * ========================================================= */
    public function purge(string $id, string $actorType = 'USER'): array
    {
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
                'message' => '鈺곕똻???? ??낅뮉 椰꾧퀡?믭㎗?륁뿯??덈뼄.'
            ];
        }

        $this->pdo->beginTransaction();

        try {

            /* =========================
             * 1?るㅄ源????뵬 ????(???뼎)
             * ========================= */

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

            /* =========================
             * 2?るㅄ源?DB ????
             * ========================= */

            $ok = $this->model->hardDeleteById($id);

            if (!$ok) {
                throw new \Exception('DB ??????쎈솭');
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
                'message' => '??????쎈솭'
            ];
        }
    }

    /* =========================================================
    * ?醫뤾문 ?袁⑹읈????
    * ========================================================= */
    public function purgeBulk(array $ids, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        if (empty($ids)) {
            return ['success' => false, 'message' => 'ID ??곸벉'];
        }

        $this->pdo->beginTransaction();

        try {

            $success = 0;

            foreach ($ids as $id) {

                /* =========================================================
                 * 1?るㅄ源?疫꿸퀣???怨쀬뵠??鈺곌퀬??
                 * ========================================================= */
                $client = $this->model->getById($id);

                if (!$client) {
                    continue;
                }

                /* =========================================================
                 * 2?るㅄ源????뵬 ????
                 * ========================================================= */
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

                /* =========================================================
                 * 3?るㅄ源?DB ????
                 * ========================================================= */
                $ok = $this->model->hardDeleteById($id);

                if ($ok) $success++;
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "?????袁⑥┷ ({$success}椰?"
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

    /* =========================================================
    * ?袁⑷퍥 ?袁⑹읈????
    * ========================================================= */
    public function purgeAll(string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->pdo->beginTransaction();

        try {

            $rows = $this->model->getDeleted();

            $success = 0;

            foreach ($rows as $row) {

                /* =========================================================
                * 1?るㅄ源????뵬 ????
                * ========================================================= */
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

                /* =========================================================
                * 2?るㅄ源?DB ????
                * ========================================================= */
                $ok = $this->model->hardDeleteById($row['id']);

                if ($ok) $success++;
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "?袁⑷퍥 ?????袁⑥┷ ({$success}椰?"
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


    /* ============================================================
    * ?꾨뗀諭???뽮퐣 癰궰野?(RowReorder)
    * ============================================================ */
    public function reorder(array $changes): bool
    {
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

            /* 1?るㅄ源???낆젾揶?野꺜筌?*/
            foreach ($changes as $row) {

                if (
                    empty($row['id']) ||
                    !isset($row['newSortNo'])
                ) {
                    throw new \Exception('reorder ?怨쀬뵠????살첒');
                }
            }

            /* 2?るㅄ源?temp ??猷?(?겸뫖猷?獄쎻뫗?) */
            foreach ($changes as $row) {

                // ?紐???곌석??띿쓺 (??? ?겸뫖猷???덇돌野?
                $tempSortNo = (int)$row['newSortNo'] + 1000000;

                $this->model->updateSortNo(
                    $row['id'],
                    $tempSortNo
                );
            }

            /* 3?るㅄ源???쇱젫 ?꾨뗀諭??怨몄뒠 */
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

    /* ============================================================
    * ??쀫탣????쇱뒲嚥≪뮆諭?
    * ============================================================ */
    public function downloadTemplate(): void
    {
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

    public function downloadExcel(): void
    {
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

    public function downloadMigrationTemplate(): void { $this->downloadTemplate(); }
    public function saveFromMigrationExcelFile(string $filePath): array { return $this->saveFromExcelFile($filePath); }
    public function downloadMigrationExcel(): void { $this->downloadExcel(); }
    private function getClientMigrationHeaders(): array
    {
        return ['거래처명', '상호명', '등록일자', '사업자등록번호', '업태', '대표자명', '전화', '이메일', '주소', '메모'];
    }

    private function getClientMigrationHeaderMap(): array
    {
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
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "{$label} ???뵬 ??몄쎗????낆쨮????쀫립???λ뜃???됰뮸??덈뼄.",
            UPLOAD_ERR_PARTIAL => "{$label} ???뵬 ??낆쨮??? 餓λ쵌而????쎈솭??됰뮸??덈뼄. ??쇰뻻 ??뺣즲??곻폒?紐꾩뒄.",
            UPLOAD_ERR_NO_TMP_DIR => "{$label} ??낆쨮??뽰뒠 ?袁⑸뻻 ???묊몴?筌≪뼚? 筌륁궢六??щ빍??",
            UPLOAD_ERR_CANT_WRITE => "{$label} ???뵬????뺤쒔?????館釉?쭪? 筌륁궢六??щ빍??",
            UPLOAD_ERR_EXTENSION => "{$label} ???뵬 ??낆쨮??? ??뺤쒔 ?類ㅼ삢 筌뤴뫀諭????묐퉸 餓λ쵎???뤿???щ빍??",
            default => "{$label} ???뵬 ??낆쨮??餓???살첒揶쎛 獄쏆뮇源??됰뮸??덈뼄.",
        };
    }
}
