<?php
namespace App\Controllers\System;

use App\Services\File\FileService;
use Core\DbPdo;

class FileController
{
    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    private function hasSessionUser(): bool
    {
        return !empty($_SESSION['user']) && !empty($_SESSION['user']['id']);
    }

    private function isAdminUser(): bool
    {
        $roles = $_SESSION['user']['roles'] ?? [];
        if (!is_array($roles)) {
            return false;
        }

        return in_array('super_admin', $roles, true) || in_array('admin', $roles, true);
    }

    public function apiPreview()
    {
        if (!$this->hasSessionUser()) {
            http_response_code(403);
            exit('Forbidden');
        }

        $dbPath = $_GET['path'] ?? '';
        if ($dbPath === '') {
            http_response_code(400);
            exit('Missing path');
        }

        $allowedPrefixes = [
            'private://id_doc/',
            'private://certificate/',
            'private://bank_file/',
            'private://card_file/',
            'public://profile/',
            'public://documents/',
            'public://business_cert/',
            'public://covers/',
            'public://brand/',
        ];

        $allowed = false;
        foreach ($allowedPrefixes as $prefix) {
            if (strpos($dbPath, $prefix) === 0) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            http_response_code(403);
            exit('Access denied');
        }

        $abs = \Core\storage_resolve_abs($dbPath);
        if (!$abs || !is_file($abs)) {
            http_response_code(404);
            exit('File not found');
        }

        $mime = mime_content_type($abs);
        $allowedMime = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
        ];

        if (!in_array($mime, $allowedMime, true)) {
            http_response_code(403);
            exit('Unsupported file type');
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($abs));
        readfile($abs);
        exit;
    }

    public function apiUploadTest()
    {
        if (!$this->hasSessionUser()) {
            $this->json(['success' => false, 'message' => '로그인이 필요합니다.'], 403);
            return;
        }

        if (empty($_FILES['file'])) {
            $this->json(['success' => false, 'message' => '테스트할 파일을 선택하세요.'], 400);
            return;
        }

        if (!empty($_POST['policy_key']) && !empty($_POST['bucket'])) {
            $this->json([
                'success' => false,
                'message' => '업로드 정책과 직접 버킷 지정은 동시에 사용할 수 없습니다.'
            ], 400);
            return;
        }

        $service = new FileService(DbPdo::conn());

        if (!empty($_POST['policy_key'])) {
            $this->json($service->uploadByPolicyKey($_FILES['file'], (string)$_POST['policy_key']));
            return;
        }

        if (empty($_POST['bucket'])) {
            $this->json([
                'success' => false,
                'message' => '버킷 또는 업로드 정책을 선택하세요.'
            ], 400);
            return;
        }

        $this->json(
            $service->upload(
                $_FILES['file'],
                (string)$_POST['bucket'],
                ['jpg', 'jpeg', 'png', 'pdf'],
                10 * 1024 * 1024
            )
        );
    }

    public function apiPolicyList()
    {
        if (!$this->hasSessionUser()) {
            $this->json(['success' => false, 'message' => '로그인이 필요합니다.'], 403);
            return;
        }

        $service = new FileService(DbPdo::conn());
        $this->json($service->listPolicies());
    }

    public function apiPolicyCreate()
    {
        if (!$this->hasSessionUser()) {
            $this->json(['success' => false, 'message' => '로그인이 필요합니다.'], 403);
            return;
        }

        $service = new FileService(DbPdo::conn());
        $data = $_POST;
        $data['created_by'] = $_SESSION['user']['id'];

        $this->json(['success' => $service->savePolicy($data)]);
    }

    public function apiPolicyUpdate()
    {
        if (!$this->hasSessionUser()) {
            $this->json(['success' => false, 'message' => '로그인이 필요합니다.'], 403);
            return;
        }

        if (empty($_POST['id'])) {
            $this->json(['success' => false, 'message' => '정책 ID가 없습니다.'], 400);
            return;
        }

        $service = new FileService(DbPdo::conn());
        $data = [
            'id'           => $_POST['id'],
            'policy_name'  => $_POST['policy_name'] ?? '',
            'policy_key'   => $_POST['policy_key'] ?? '',
            'bucket'       => $_POST['bucket'] ?? '',
            'allowed_ext'  => $_POST['allowed_ext'] ?? '',
            'allowed_mime' => $_POST['allowed_mime'] ?? null,
            'max_size_mb'  => (int)($_POST['max_size_mb'] ?? 0),
            'is_active'    => (int)($_POST['is_active'] ?? 0),
            'description'  => $_POST['description'] ?? null,
            'updated_by'   => $_SESSION['user']['id'],
        ];

        $this->json(['success' => $service->updatePolicy($data)]);
    }

    public function apiPolicyDelete()
    {
        if (!$this->hasSessionUser()) {
            $this->json(['success' => false, 'message' => '로그인이 필요합니다.'], 403);
            return;
        }

        if (empty($_POST['id'])) {
            $this->json(['success' => false, 'message' => '정책 ID가 없습니다.'], 400);
            return;
        }

        $service = new FileService(DbPdo::conn());
        $this->json(['success' => $service->deletePolicy((string)$_POST['id'])]);
    }

    public function apiPolicyToggle()
    {
        if (!$this->hasSessionUser()) {
            $this->json(['success' => false, 'message' => '로그인이 필요합니다.'], 403);
            return;
        }

        if (!isset($_POST['id'], $_POST['is_active'])) {
            $this->json(['success' => false, 'message' => '잘못된 요청입니다.'], 400);
            return;
        }

        $service = new FileService(DbPdo::conn());
        $success = $service->setPolicyActive(
            (string)$_POST['id'],
            (int)$_POST['is_active'],
            (string)$_SESSION['user']['id']
        );

        $this->json(['success' => $success]);
    }

    public function apiBucketBrowse()
    {
        if (!$this->hasSessionUser()) {
            $this->json(['success' => false, 'message' => '로그인이 필요합니다.'], 403);
            return;
        }

        if (!$this->isAdminUser()) {
            $this->json(['success' => false, 'message' => '관리자만 사용할 수 있는 기능입니다.'], 403);
            return;
        }

        $bucket = $_GET['bucket'] ?? '';
        if ($bucket === '') {
            $this->json(['success' => false, 'message' => '버킷 정보가 없습니다.'], 400);
            return;
        }

        $map = \Core\storage_bucket_map();
        if (!isset($map[$bucket])) {
            $this->json(['success' => false, 'message' => '존재하지 않는 버킷입니다.'], 404);
            return;
        }

        $dir = $map[$bucket];
        if (!is_dir($dir)) {
            $this->json(['success' => false, 'message' => '버킷 디렉터리를 찾을 수 없습니다.'], 404);
            return;
        }

        $files = [];
        foreach (scandir($dir) as $fileName) {
            if ($fileName === '.' || $fileName === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $fileName;
            $files[] = [
                'name' => $fileName,
                'type' => is_dir($path) ? 'dir' : 'file',
                'size' => is_file($path) ? filesize($path) : null,
                'mtime' => filemtime($path),
                'db_path' => is_file($path) ? rtrim($bucket, '/') . '/' . $fileName : null,
            ];
        }

        $this->json([
            'success' => true,
            'bucket' => $bucket,
            'files' => $files,
        ]);
    }
}
