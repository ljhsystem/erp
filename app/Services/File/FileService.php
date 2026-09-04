<?php
namespace App\Services\File;

use PDO;
use App\Models\System\FileUploadPoliciesModel;
use function Core\storage_upload;
use function Core\storage_delete;
use function Core\storage_resolve_abs;
use function Core\storage_to_url;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;

class FileService
{
    private readonly PDO $pdo;
    private FileUploadPoliciesModel $policyModel;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->logger = LoggerFactory::getLogger('service-file-file');
        $this->policyModel = new FileUploadPoliciesModel($pdo);
    }

    private function runUpload(
        array $file,
        string $bucket,
        array $extList,
        int $maxSize,
        array $mimeList,
        bool $writeLog = true
    ): array {
        try {
            $result = storage_upload($file, $bucket, $extList, $maxSize, $mimeList);
        } catch (\Throwable $exception) {
            if ($writeLog) $this->logger->error('파일 업로드에 실패했습니다.', ['event_code'=>'FILE_UPLOAD_FAILED','result'=>'FAILED','service'=>self::class,'action'=>'upload','bucket_code'=>$this->bucketCode($bucket),'file_size'=>(int)($file['size']??0),'error_code'=>get_class($exception),'error'=>$exception]);
            throw $exception;
        }

        if ($writeLog) {
            $success = (bool) ($result['success'] ?? false);
            $this->logger->{$success?'info':'warning'}($success?'파일 업로드를 완료했습니다.':'파일 업로드가 차단되었습니다.', [
                'event_code'=>$success?'FILE_UPLOAD_COMPLETED':'FILE_UPLOAD_BLOCKED','result'=>$success?'SUCCESS':'BLOCKED','service'=>self::class,'action'=>'upload','bucket_code'=>$this->bucketCode($bucket),'file_size'=>(int)($result['size']??$file['size']??0),'mime_type'=>$success?($result['mime']??null):null,'reason_code'=>$success?null:($result['code']??'UPLOAD_REJECTED')
            ]);
        }

        return $result;
    }

    public function uploadProfile(array $file): array
    {
        return $this->runUpload(
            $file,
            'public://profile',
            ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            5 * 1024 * 1024,
            ['image/jpeg', 'image/png', 'image/webp', 'image/gif']
        );
    }

    public function uploadBusinessCert(array $file): array
    {
        return $this->runUpload(
            $file,
            'public://business_cert',
            ['jpg', 'jpeg', 'png', 'pdf'],
            10 * 1024 * 1024,
            ['image/jpeg', 'image/png', 'application/pdf']
        );
    }

    public function uploadCertificate(array $file): array
    {

        $bucket   = 'private://certificate';
        $extList  = ['jpg', 'jpeg', 'png', 'pdf'];
        $mimeList = ['image/jpeg', 'image/png', 'application/pdf'];
        $maxSize  = 10 * 1024 * 1024; // 10MB

        return $this->runUpload($file, $bucket, $extList, $maxSize, $mimeList);
    }

    public function uploadBankCopy(array $file): array
    {
        return $this->runUpload(
            $file,
            'private://bank_file',
            ['jpg', 'jpeg', 'png', 'pdf'],
            10 * 1024 * 1024,
            ['image/jpeg', 'image/png', 'application/pdf']
        );
    }

    public function uploadCardCopy(array $file): array
    {
        return $this->runUpload(
            $file,
            'private://card_file',
            ['jpg', 'jpeg', 'png', 'pdf'],
            10 * 1024 * 1024,
            ['image/jpeg', 'image/png', 'application/pdf']
        );
    }

    public function uploadDocument(array $file): array
    {
        return $this->runUpload(
            $file,
            'public://documents',
            ['jpg', 'jpeg', 'png', 'pdf', 'xls', 'xlsx'],
            10 * 1024 * 1024,
            [
                'image/jpeg',
                'image/png',
                'application/pdf',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ]
        );
    }

    public function uploadPrivateIdDoc(array $file): array
    {

        $result = $this->runUpload(
            $file,
            'private://id_doc',
            ['jpg', 'jpeg', 'png', 'pdf'],
            10 * 1024 * 1024,
            ['image/jpeg', 'image/png', 'application/pdf']
        );

        if (!empty($result['success']) && isset($result['db_path'])) {

            $result['db_path'] = str_replace(
                '/storage/uploads/id_doc/',
                'private://id_doc/',
                $result['db_path']
            );
        }

        return $result;
    }

    public function uploadRaw(array $file): array
    {
        return $this->runUpload(
            $file,
            'private://raw',
            ['jpg', 'jpeg', 'png', 'pdf', 'zip', 'csv', 'txt'],
            20 * 1024 * 1024,
            [
                'image/jpeg',
                'image/png',
                'application/pdf',
                'application/zip',
                'text/plain',
                'text/csv'
            ]
        );
    }

    public function upload(array $file, string $bucket, array $extList, int $size, array $mimeList = []): array
    {
        return $this->runUpload($file, $bucket, $extList, $size, $mimeList);
    }

    public function delete(?string $dbPath): bool
    {
        return $this->deleteFile($dbPath, true);
    }

    private function deleteFile(?string $dbPath, bool $writeLog): bool
    {
        $pathRef=$this->pathRef($dbPath);
        if (!$dbPath) {
            if($writeLog)$this->logger->warning('파일 삭제가 차단되었습니다.',['event_code'=>'FILE_DELETE_BLOCKED','result'=>'BLOCKED','service'=>self::class,'action'=>'delete','reason_code'=>'PATH_REQUIRED']);
            return false;
        }

        $abs = storage_resolve_abs($dbPath);
        if (!$abs || !is_file($abs)) {
            if($writeLog)$this->logger->warning('파일 삭제가 차단되었습니다.',['event_code'=>'FILE_DELETE_BLOCKED','result'=>'BLOCKED','service'=>self::class,'action'=>'delete','path_ref'=>$pathRef,'reason_code'=>'FILE_NOT_FOUND']);
            return false;
        }

        $success = @unlink($abs);
        if($writeLog)$this->logger->{$success?'info':'error'}($success?'파일 삭제를 완료했습니다.':'파일 삭제에 실패했습니다.',['event_code'=>$success?'FILE_DELETE_COMPLETED':'FILE_DELETE_FAILED','result'=>$success?'SUCCESS':'FAILED','service'=>self::class,'action'=>'delete','path_ref'=>$pathRef]);

        return $success;
    }

    public function resolveAbsolute(string $dbPath): ?string
    {
        return storage_resolve_abs($dbPath);
    }

    public function url(string $dbPath): ?string
    {
        return storage_to_url($dbPath);
    }

    public function replace(
        ?string $oldDbPath,
        array $newFile,
        string $bucket,
        array $extList,
        int $size,
        array $mimeList = []
    ): array {

        $upload = $this->runUpload($newFile, $bucket, $extList, $size, $mimeList, false);

        if (empty($upload['success'])) {
            $this->logger->warning('파일 교체가 차단되었습니다.',['event_code'=>'FILE_REPLACE_BLOCKED','result'=>'BLOCKED','service'=>self::class,'action'=>'replace','old_path_ref'=>$this->pathRef($oldDbPath),'bucket_code'=>$this->bucketCode($bucket),'reason_code'=>$upload['code']??'UPLOAD_REJECTED']);

            return [
                'success' => false,
                'code'    => $upload['code'] ?? 'upload_failed',
                'message' => $upload['message'] ?? '업로드 실패'
            ];
        }

        if ($oldDbPath) {
            $this->deleteFile($oldDbPath, false);
        }

        $result = [
            'success'  => true,
            'code'     => 'ok',
            'message'  => '파일 교체 완료',
            'db_path'  => $upload['db_path'],
            'abs_path' => $upload['abs'],
            'file'     => $upload['file'],
            'mime'     => $upload['mime'],
            'size'     => $upload['size'],
        ];

        $this->logger->info('파일 교체를 완료했습니다.',['event_code'=>'FILE_REPLACE_COMPLETED','result'=>'SUCCESS','service'=>self::class,'action'=>'replace','old_path_ref'=>$this->pathRef($oldDbPath),'new_path_ref'=>$this->pathRef((string)$upload['db_path']),'bucket_code'=>$this->bucketCode($bucket),'file_size'=>(int)($upload['size']??0),'mime_type'=>$upload['mime']??null]);

        return $result;
    }

    public function uploadBrandLogo(array $file): array
    {
        return $this->runUpload(
            $file,
            'public://brand',
            ['jpg', 'jpeg', 'png', 'svg', 'ico', 'webp'],
            5 * 1024 * 1024,
            [
                'image/jpeg',
                'image/png',
                'image/svg+xml',
                'image/x-icon',
                'image/vnd.microsoft.icon',
                'image/webp'
            ]
        );
    }

    public function uploadByPolicyKey(array $file, string $policyKey): array
    {

        $policy = $this->policyModel->findByKey($policyKey);

        if ($policy) {
            if ((int)$policy['is_active'] !== 1) {
                return [
                    'success' => false,
                    'message' => '비활성화된 업로드 정책입니다.'
                ];
            }

            return $this->runUpload(
                $file,
                $policy['bucket'],
                explode(',', $policy['allowed_ext']),
                (int)$policy['max_size_mb'] * 1024 * 1024,
                $policy['allowed_mime']
                    ? explode(',', $policy['allowed_mime'])
                    : []
            );
        }

        return match ($policyKey) {
            'profile_image'  => $this->uploadProfile($file),
            'business_cert'  => $this->uploadBusinessCert($file),
            'certificate'    => $this->uploadCertificate($file),
            'private_id_doc' => $this->uploadPrivateIdDoc($file),
            'document'       => $this->uploadDocument($file),
            default => [
                'success' => false,
                'message' => '업로드 정책이 정의되지 않았습니다.'
            ]
        };
    }

    public function listPolicies(): array
    {
        return $this->policyModel->getAll();
    }

    public function savePolicy(array $data): bool
    {

        if (!empty($data['id'])) {
            $data['updated_by'] = ActorHelper::user();
            $ok=$this->policyModel->update($data['id'], $data);$this->logPolicyResult('FILE_POLICY_UPDATE',$ok,(string)$data['id']);return$ok;
        }

        $data['id'] = UuidHelper::generate();
        $data['created_by'] = ActorHelper::user();

        $ok=$this->policyModel->create($data);$this->logPolicyResult('FILE_POLICY_CREATE',$ok,(string)$data['id']);return$ok;
    }

    public function updatePolicy(array $data): bool
    {
        if (empty($data['id'])) {
            return false;
        }

        $data['updated_by'] = ActorHelper::user();
        $ok=$this->policyModel->update($data['id'], $data);$this->logPolicyResult('FILE_POLICY_UPDATE',$ok,(string)$data['id']);return$ok;
    }

    public function deletePolicy(string $id): bool
    {
        $ok=$this->policyModel->delete($id);$this->logPolicyResult('FILE_POLICY_DELETE',$ok,$id);return$ok;
    }

    public function setPolicyActive(string $id, int $isActive): bool
    {
        $ok=$this->policyModel->setActive($id,(bool)$isActive,ActorHelper::user());$this->logPolicyResult('FILE_POLICY_ACTIVE_CHANGE',$ok,$id,['is_active'=>$isActive]);return$ok;
    }

    private function logPolicyResult(string $event,bool $success,string $id,array $context=[]):void{$this->logger->{$success?'info':'error'}($success?'파일정책 업무 처리를 완료했습니다.':'파일정책 업무 처리에 실패했습니다.',['event_code'=>$event.($success?'':'_FAILED'),'result'=>$success?'SUCCESS':'FAILED','service'=>self::class,'action'=>strtolower($event),'target_id'=>$id]+$context);}
    private function pathRef(?string $path):?string{return $path?substr(hash('sha256',$path),0,16):null;}
    private function bucketCode(string $bucket):string{return strtoupper((string)preg_replace('/[^a-z0-9]+/i','_',trim($bucket)));}
}
