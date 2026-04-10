<?php
// 경로: PROJECT_ROOT/app/Controllers/System/FileController.php
namespace App\Controllers\System;

use Core\DbPdo;
use App\Services\File\FileService;

/**
 * ============================================================
 * FILE CONTROLLER
 * 파일 미리보기, 업로드 테스트, 업로드 정책 관리 API
 * ============================================================
 */
class FileController
{
    // ============================================================
    // API: 파일 미리보기
    // URL: GET /api/file/preview?path={db_path}
    // permission: api.file.apipreview
    // controller: FileController@apipreview
    // ============================================================
    public function apiPreview()
    {
        if (empty($_SESSION['user'])) {
            http_response_code(403);
            exit('Forbidden');
        }

        $dbPath = $_GET['path'] ?? '';
        if (!$dbPath) {
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

    // ============================================================
    // API: 파일 업로드 테스트
    // URL: POST /api/file/upload-test
    // permission: api.file.upload.test
    // controller: FileController@uploadTest
    // ============================================================
    public function apiUploadTest()
    {
        if (empty($_SESSION['user'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            return;
        }

        if (empty($_FILES['file'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => '파일 없음']);
            return;
        }

        // 🚫 정책 + 버킷 동시 사용 금지
        if (!empty($_POST['policy_key']) && !empty($_POST['bucket'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => '업로드 정책과 저장 버킷은 동시에 사용할 수 없습니다.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $service = new FileService(DbPdo::conn());

        // 1️⃣ 정책 기반 업로드 (운영 시뮬레이션)
        if (!empty($_POST['policy_key'])) {
            echo json_encode(
                $service->uploadByPolicyKey(
                    $_FILES['file'],
                    $_POST['policy_key']
                ),
                JSON_UNESCAPED_UNICODE
            );
            return;
        }

        // 2️⃣ 버킷 직접 업로드 (관리자 테스트)
        if (empty($_POST['bucket'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Bucket 또는 정책 필요'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode(
            $service->upload(
                $_FILES['file'],
                $_POST['bucket'],
                ['jpg', 'jpeg', 'png', 'pdf'],
                10 * 1024 * 1024
            ),
            JSON_UNESCAPED_UNICODE
        );
    }


    // ============================================================
    // API: 파일 업로드 정책 목록 조회
    // URL: GET /api/system/file-policies
    // permission: api.settings.system.storage.policy.view
    // controller: FileController@apiPolicyList
    // ============================================================
    public function apiPolicyList()
    {
        header('Content-Type: application/json; charset=utf-8');

        if (empty($_SESSION['user'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            return;
        }

        $service = new FileService(DbPdo::conn());

        echo json_encode(
            $service->listPolicies(),
            JSON_UNESCAPED_UNICODE
        );
    }


    // ============================================================
    // API: 파일 업로드 정책 생성
    // URL: POST /api/system/file-policies
    // permission: api.settings.system.storage.policy.create
    // controller: FileController@apiPolicyCreate
    // ============================================================
    public function apiPolicyCreate()
    {
        header('Content-Type: application/json; charset=utf-8');

        $service = new FileService(DbPdo::conn());
        if (empty($_SESSION['user']['id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            return;
        }

        $data = $_POST;
        $data['created_by'] = $_SESSION['user']['id'];

        $success = $service->savePolicy($data);

        echo json_encode(['success' => $success], JSON_UNESCAPED_UNICODE);
    }
    // ============================================================
    // API: 파일 업로드 정책 수정
    // URL: POST /api/system/file-policies/update
    // permission: api.settings.system.storage.policy.edit
    // controller: FileController@apiPolicyUpdate
    // ============================================================
    public function apiPolicyUpdate()
    {
        header('Content-Type: application/json; charset=utf-8');

        if (empty($_POST['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'id 없음']);
            return;
        }

        $service = new FileService(DbPdo::conn());

        if (empty($_SESSION['user']['id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            return;
        }

        $data = [
            'id'           => $_POST['id'],
            'policy_name'  => $_POST['policy_name'] ?? '',
            'policy_key'  => $_POST['policy_key'] ?? '',
            'bucket'       => $_POST['bucket'] ?? '',
            'allowed_ext'  => $_POST['allowed_ext'] ?? '',
            'allowed_mime' => $_POST['allowed_mime'] ?? null,
            'max_size_mb'  => (int)($_POST['max_size_mb'] ?? 0),
            'is_active'    => (int)($_POST['is_active'] ?? 0),
            'description'  => $_POST['description'] ?? null,
            'updated_by'   => $_SESSION['user']['id'],
        ];


        $success = $service->updatePolicy($data);



        echo json_encode(['success' => $success], JSON_UNESCAPED_UNICODE);
    }


    // ============================================================
    // API: 파일 업로드 정책 삭제
    // URL: POST /api/system/file-policies/delete
    // permission: api.settings.system.storage.policy.delete
    // controller: FileController@apiPolicyDelete
    // ============================================================
    public function apiPolicyDelete()
    {
        header('Content-Type: application/json; charset=utf-8');

        if (empty($_SESSION['user']['id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            return;
        }

        if (empty($_POST['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'id 없음']);
            return;
        }


        $service = new FileService(DbPdo::conn());
        $success = $service->deletePolicy($_POST['id']); // UUID

        echo json_encode(['success' => $success], JSON_UNESCAPED_UNICODE);
    }
    // ============================================================
    // API: 파일 업로드 정책 활성/비활성
    // URL: POST /api/system/file-policies/toggle
    // permission: api.settings.system.storage.policy.toggle
    // controller: FileController@apiPolicyToggle
    // ============================================================
    public function apiPolicyToggle()
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!isset($_POST['id'], $_POST['is_active'])) {
            echo json_encode([
                'success' => false,
                'message' => 'invalid params'
            ]);
            return;
        }

        $service = new FileService(DbPdo::conn());

        if (empty($_SESSION['user']['id'])) {
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            return;
        }

        $success = $service->setPolicyActive(
            $_POST['id'],
            (int)$_POST['is_active'],
            $_SESSION['user']['id']
        );


        echo json_encode(['success' => $success]);
    }


    // ============================================================
    // API: 버킷 폴더 조회
    // URL: GET /api/system/storage/bucket-browse?bucket=public://documents
    // permission: api.settings.system.storage.browse
    // controller: FileController@apiBucketBrowse
    // ============================================================
    public function apiBucketBrowse()
    {
        header('Content-Type: application/json; charset=utf-8');

        $bucket = $_GET['bucket'] ?? '';
        if (!$bucket) {
            echo json_encode([
                'success' => false,
                'message' => 'bucket 파라미터 없음'
            ]);
            return;
        }

        // bucket → 실제 경로 변환
        $map = \Core\storage_bucket_map();
        if (!isset($map[$bucket])) {
            echo json_encode([
                'success' => false,
                'message' => '존재하지 않는 bucket'
            ]);
            return;
        }

        $dir = $map[$bucket];
        if (!is_dir($dir)) {
            echo json_encode([
                'success' => false,
                'message' => '디렉토리 없음'
            ]);
            return;
        }

        $files = [];
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') continue;

            $path = $dir . DIRECTORY_SEPARATOR . $f;

            $files[] = [
                'name'  => $f,
                'type'  => is_dir($path) ? 'dir' : 'file',
                'size'  => is_file($path) ? filesize($path) : null,
                'mtime' => filemtime($path),

                // ⭐ JS 더블클릭용 핵심
                'db_path' => is_file($path)
                    ? rtrim($bucket, '/') . '/' . $f
                    : null
            ];
        }

        echo json_encode([
            'success' => true,
            'bucket'  => $bucket,
            'path'    => $dir,
            'files'   => $files
        ], JSON_UNESCAPED_UNICODE);
    }
}
