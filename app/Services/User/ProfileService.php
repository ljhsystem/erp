<?php
namespace App\Services\User;

use PDO;
use App\Models\Auth\UserModel;
use App\Models\User\EmployeeModel;
use App\Services\File\FileService;
use App\Services\Concerns\LogsServiceOperations;
use Core\Helpers\ActorHelper;
use Core\Security\Crypto;
use Core\LoggerFactory;
use Psr\Log\LoggerInterface;

class ProfileService
{
    use LogsServiceOperations;
    private readonly PDO $pdo;
    private $users;
    private $employees;
    private $fileService;
    private LoggerInterface $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->users       = new UserModel($pdo);
        $this->employees   = new EmployeeModel($pdo); // 🔥 변경
        $this->fileService = new FileService($pdo);
        $this->logger      = LoggerFactory::getLogger("service-user.ProfileService");
    }

    public function getById(string $userId): ?array
    {
        $user = $this->users->getById($userId);
        if (!$user) return null;

        $rows = $this->employees->getList();

        $employeeId = null;

        foreach ($rows as $row) {
            if (($row['user_id'] ?? null) === $userId) {
                $employeeId = $row['id'];
                break;
            }
        }

        if (!$employeeId) {
            throw new \RuntimeException('사용자와 연결된 직원 정보를 찾을 수 없습니다.');
        }

        $employee = null;
        if ($employeeId) {
            $employee = $this->employees->getById($employeeId);
        }

        return array_merge($user, $employee ?? []);
    }

    public function getCurrentProfile(): ?array
    {
        $actor = ActorHelper::parse(ActorHelper::user());
        $userId = $actor['id'] ?? null;

        if (!$userId) {
            throw new \RuntimeException('로그인 정보가 필요합니다.');
        }

        return $this->getById($userId);
    }

    public function save(array $data, array $files = []): array
    {
        return $this->runLoggedOperation(
            $this->logger,
            '사용자 프로필 저장',
            'USER_PROFILE_SAVE',
            'save',
            [],
            fn(): array => $this->saveInternal($data, $files),
            'info',
            false,
            static fn(array $result): string => !empty($result['success']) ? 'SUCCESS' : (((string) ($result['error_type'] ?? '')) === 'system' ? 'FAILED' : 'BLOCKED')
        );
    }

    private function saveInternal(array $data, array $files = []): array
    {
        $actor  = ActorHelper::resolve('USER');
        $userId = $data['id'] ?? null;

        if (!$userId) {
            return ['success' => false, 'message' => '사용자 정보가 필요합니다.'];
        }

        $deleteAfterCommit = [];

        try {
            $rows = $this->employees->getList();

            $employee = null;
            foreach ($rows as $row) {
                if (($row['user_id'] ?? null) === $userId) {
                    $employee = $row;
                    break;
                }
            }

            if (!$employee) {
                return ['success' => false, 'message' => '프로필 정보를 찾을 수 없습니다.'];
            }

            $employeeId = $employee['id'];

            $authData = [];

            if (array_key_exists('email', $data)) {
                $authData['email'] = trim((string)$data['email']);
            }

            if (!empty($data['new_password'])) {
                if (empty($data['current_password'])) {
                    throw new \InvalidArgumentException('현재 비밀번호를 입력해 주세요.');
                }

                if ($data['new_password'] !== ($data['confirm_password'] ?? '')) {
                    throw new \InvalidArgumentException('새 비밀번호와 확인 비밀번호가 일치하지 않습니다.');
                }

                $authData['password'] = password_hash($data['new_password'], PASSWORD_DEFAULT);
                $authData['password_updated_at'] = date('Y-m-d H:i:s');
                $authData['password_updated_by'] = $actor;
            }

            if (isset($data['two_factor_enabled'])) {
                $authData['two_factor_enabled'] = (int)$data['two_factor_enabled'];
            }

            if (isset($data['email_notify'])) {
                $authData['email_notify'] = (int)$data['email_notify'];
            }

            if (isset($data['sms_notify'])) {
                $authData['sms_notify'] = (int)$data['sms_notify'];
            }

            if (!empty($authData)) {
                $authData['updated_by'] = $actor;
            }

            $profileData = [];

            $fields = [
                'employee_name',
                'phone',
                'address',
                'address_detail',
                'note',
                'memo',
                'emergency_phone'
            ];

            foreach ($fields as $f) {
                if (array_key_exists($f, $data)) {
                    $profileData[$f] = $data[$f] === '' ? null : $data[$f];
                }
            }

            if (!empty($data['rrn']) && strpos((string)$data['rrn'], '*') === false) {
                $crypto = new Crypto();
                $profileData['rrn'] = $crypto->encryptResidentNumber(
                    preg_replace('/\D+/', '', (string)$data['rrn'])
                );
            }

            $currentProfile = $employee['profile_image'] ?? null;

            // 프로필 이미지
            if (!empty($files['profile_image']['name'])) {
                $upload = $this->fileService->uploadProfile($files['profile_image']);

                if (empty($upload['success'])) {
                    return [
                        'success' => false,
                        'message' => '프로필 이미지 업로드 중 오류가 발생했습니다.',
                        'error_type' => 'system'
                    ];
                }

                $profileData['profile_image'] = $upload['db_path'];

                if (!empty($currentProfile) && $currentProfile !== $profileData['profile_image']) {
                    $deleteAfterCommit[] = $currentProfile;
                }
            } else {
                $profileData['profile_image'] = $currentProfile;
            }

            $profileData['profile_image'] = $profileData['profile_image'] ?? $currentProfile;

            if (!empty($profileData)) {
                $profileData['updated_by'] = $actor;
            }

            $this->pdo->beginTransaction();

            if (!empty($authData)) {
                $this->users->updateUserDirect($userId, $authData);
            }

            if (!empty($profileData)) {

                $current = $this->employees->getById($employeeId);

                if (!$current) {
                    throw new \RuntimeException('직원 정보를 찾을 수 없습니다.');
                }

                $updateData = array_merge($current, $profileData);

                unset(
                    $updateData['id'],
                    $updateData['created_at'],
                    $updateData['created_by'],
                    $updateData['updated_at']
                );

                $updateData['updated_by'] = $actor;

                $this->employees->updateById($employeeId, $updateData);
            }

            $this->pdo->commit();

            foreach ($deleteAfterCommit as $oldFile) {
                try {
                    $this->fileService->delete($oldFile);
                } catch (\Throwable $deleteError) {
                    $this->logger->warning('기존 프로필 이미지 정리가 지연되었습니다.', [
                        'event_code' => 'USER_PROFILE_OLD_IMAGE_DELETE_BLOCKED',
                        'result' => 'BLOCKED',
                        'error_code' => get_class($deleteError),
                        'error' => $deleteError,
                    ]);
                }
            }

            return [
                'success'          => true,
                'message'          => '프로필을 저장했습니다.',
                'profile_image'    => $profileData['profile_image'] ?? $currentProfile,
            ];

        } catch (\InvalidArgumentException|\DomainException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => 'business'
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return [
                'success' => false,
                'message' => '저장 중 오류가 발생했습니다.',
                'error_type' => 'system'
            ];
        }
    }

    public function saveCurrent(array $data, array $files = []): array
    {
        $actor = ActorHelper::parse(ActorHelper::user());
        $userId = $actor['id'] ?? null;

        if (!$userId) {
            throw new \RuntimeException('로그인이 필요합니다.');
        }

        $data['id'] = $userId;

        return $this->save($data, $files);
    }


}
