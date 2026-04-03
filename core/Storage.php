<?php
// 경로: PROJECT_ROOT . '/core/Storage.php'
// 설명: 파일 저장 코어 기능만 담당. 정책/비즈니스 로직 제외.
//  - Core 네임스페이스에 실제 구현
//  - 전역(global) 네임스페이스에 헬퍼 래퍼를 두어서
//    다른 네임스페이스에서도 단순히 storage_upload() 등으로 호출 가능하게 설계

/* ============================================================
 * 1. Core 네임스페이스: 실제 구현부
 * ============================================================ */
namespace Core;

//use Core\LoggerFactory;

/**
 * 내부용 로거 반환.
 * Core\storage_log() 로 사용.
 */
if (!function_exists(__NAMESPACE__ . '\storage_log')) {
    // function storage_log()    {
    //     static $logger = null;
    //     if ($logger === null) {
    //         $logger = LoggerFactory::getLogger('core-Storage');
    //     }
    //     return $logger;
    // }
//     🔥 이유

// 👉 Storage는 최하위(Core Root)

// Logger 쓰면 안됨
// DB 쓰면 안됨
// Session 쓰면 안됨

// 👉 “의존성 0” 이어야 정상
    function storage_log()
    {
        return new class {
            public function info() {}
            public function warning() {}
            public function error() {}
        };
    }
}

/* ---------------------------------------------------------------
 * 1. 기본 디렉터리 상수 정의
 *    - define() 은 전역 상수이므로 네임스페이스와 무관하게 사용 가능
 * --------------------------------------------------------------- */
// ⚠️ 신규 코드에서는 storage_system_path() 사용 권장
if (!defined('PUBLIC_DIR'))         define('PUBLIC_DIR', PROJECT_ROOT . '/public');
if (!defined('PUBLIC_UPLOADS'))     define('PUBLIC_UPLOADS', PUBLIC_DIR . '/uploads');
if (!defined('STORAGE_ROOT'))       define('STORAGE_ROOT', PROJECT_ROOT . '/storage');
if (!defined('STORAGE_UPLOADS'))    define('STORAGE_UPLOADS', STORAGE_ROOT . '/uploads');
// @deprecated use storage_system_path('logs')
if (!defined('LOGS_DIR'))           define('LOGS_DIR', STORAGE_ROOT . '/logs');
// @deprecated use storage_system_path('db_backup')
if (!defined('STORAGE_DB_BACKUP'))  define('STORAGE_DB_BACKUP', STORAGE_ROOT . '/db_backup');


/* ---------------------------------------------------------------
 * 2. bucket → 실제 디렉터리 매핑
 *    (향후 DB 기반 설정으로 확장 가능)
 * --------------------------------------------------------------- */
function storage_bucket_map(): array
{
    return [
        // Public
        'public://profile'       => PUBLIC_UPLOADS . '/profile',
        'public://covers'        => PUBLIC_UPLOADS . '/covers',
        'public://business_cert' => PUBLIC_UPLOADS . '/business_cert',
        'public://documents'     => PUBLIC_UPLOADS . '/documents',
        'public://brand'         => PUBLIC_UPLOADS . '/brand',

        // Private
        'private://certificate'  => STORAGE_UPLOADS . '/certificate',
        'private://id_doc'       => STORAGE_UPLOADS . '/id_doc',
        'private://raw'          => STORAGE_UPLOADS . '/raw',
        'private://bank_copy'    => STORAGE_UPLOADS . '/bank_copy',
    ];
}


/* ---------------------------------------------------------------
 * 3. 필요한 디렉터리 생성
 * --------------------------------------------------------------- */
function storage_init_dirs(): void
{
    storage_log()->info("📁 storage_init_dirs() 호출됨");

    /* ----------------------------------------
     * 1. 업로드 버킷 디렉터리
     * ---------------------------------------- */
    foreach (storage_bucket_map() as $bucket => $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
            storage_log()->info("📁 버킷 디렉터리 생성됨", [
                'bucket' => $bucket,
                'path'   => $dir
            ]);
        }
    }

    /* ----------------------------------------
     * 2. 시스템 전용 디렉터리
     * ---------------------------------------- */
    foreach (storage_system_paths() as $key => $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
            storage_log()->info("📁 시스템 디렉터리 생성됨", [
                'key'  => $key,
                'path' => $dir
            ]);
        }
    }
}


// 파일 로드 시 1회 실행
storage_init_dirs();


/* ---------------------------------------------------------------
 * 4. 파일 업로드 (실제 코어 로직)
 * --------------------------------------------------------------- */
/**
 * @param array  $file       $_FILES['xxx'] 형태의 배열
 * @param string $bucket     storage_bucket_map() 키
 * @param array  $allowedExt 허용 확장자 (소문자)
 * @param int    $maxBytes   최대 허용 바이트
 * @param array  $allowedMime 허용 MIME 리스트 (비우면 MIME 검사 생략)
 */
function storage_upload(array $file, string $bucket, array $allowedExt, int $maxBytes, array $allowedMime = []): array
{
    storage_log()->info("📤 파일 업로드 요청", [
        'bucket'    => $bucket,
        'orig_name' => $file['name'] ?? null,
        'size'      => $file['size'] ?? null,
    ]);

    // 4-1. 기본 오류 검사
    $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($errorCode !== UPLOAD_ERR_OK) {
        $code = 'upload_error';
        $message = '파일 업로드 오류';

        // 좀 더 구체적으로 구분
        switch ($errorCode) {
            case UPLOAD_ERR_NO_FILE:
                $code = 'no_file';
                $message = '업로드된 파일이 없습니다.';
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $code = 'file_too_large';
                $message = '업로드 허용 용량을 초과했습니다.';
                break;
            default:
                // 나머지는 기본 메시지 유지
                break;
        }

        storage_log()->warning("⚠ {$code}", ['error_code' => $errorCode]);

        return [
            'success' => false,
            'code'    => $code,
            'message' => $message,
        ];
    }

    // 4-2. 용량 검사
    $size = (int)($file['size'] ?? 0);
    if ($size > $maxBytes) {
        storage_log()->warning("⚠ file_too_large", [
            'size' => $size,
            'max'  => $maxBytes
        ]);

        return [
            'success' => false,
            'code'    => 'file_too_large',
            'message' => '파일 용량 초과',
        ];
    }

    // 4-3. 확장자 검사
    $orig = basename($file['name'] ?? '');
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExt, true)) {
        storage_log()->warning("⚠ invalid_extension", [
            'ext'       => $ext,
            'allowed'   => $allowedExt,
            'orig_name' => $orig,
        ]);

        return [
            'success' => false,
            'code'    => 'invalid_extension',
            'message' => '허용되지 않은 확장자',
        ];
    }

    // 4-4. bucket 매핑
    $map = storage_bucket_map();
    if (!isset($map[$bucket])) {
        storage_log()->error("❌ invalid_bucket", ['bucket' => $bucket]);

        return [
            'success' => false,
            'code'    => 'invalid_bucket',
            'message' => '잘못된 저장 대상',
        ];
    }

    $dir = $map[$bucket];
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        storage_log()->info("📁 버킷 디렉터리 생성됨", ['path' => $dir]);
    }

    // 🔥 디버깅 로그 추가: 저장 경로 확인
    storage_log()->info("📂 파일 저장 경로", ['dir' => $dir]);

    // 4-5. MIME 검사
    $tmp  = $file['tmp_name'] ?? null;
    $mime = null;

    if ($tmp && is_file($tmp)) {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $tmp) ?: null;
                finfo_close($finfo);
            }
        }
    }

    if (!empty($allowedMime) && $mime === null) {
        storage_log()->error("❌ MIME 감지 실패", ['tmp' => $tmp]);

        return [
            'success' => false,
            'code'    => 'mime_detect_failed',
            'message' => 'MIME 타입 감지 불가',
        ];
    }

    if (!empty($allowedMime) && !in_array($mime, $allowedMime, true)) {
        storage_log()->warning("⚠ invalid_mime", [
            'mime'    => $mime,
            'allowed' => $allowedMime,
        ]);

        return [
            'success' => false,
            'code'    => 'invalid_mime',
            'message' => '허용되지 않은 MIME 타입',
        ];
    }

    // 4-6. 파일명 생성
    $fileName = uniqid('f_', true) . '.' . $ext;
    $abs      = rtrim($dir, '/\\') . '/' . $fileName;

    // 4-7. 업로드 이동
    if (!move_uploaded_file($tmp, $abs)) {
        storage_log()->error("❌ move_failed", [
            'tmp'  => $tmp,
            'dest' => $abs,
        ]);

        return [
            'success' => false,
            'code'    => 'move_failed',
            'message' => '파일 이동 실패',
        ];
    }

    // 4-8. DB 경로 생성 (PROJECT_ROOT 기준 상대경로)
    $dbPath = $bucket . '/' . $fileName;

    storage_log()->info("✅ 업로드 성공", [
        'file'    => $fileName,
        'mime'    => $mime,
        'size'    => $size,
        'db_path' => $dbPath,
    ]);

    return [
        'success' => true,
        'code'    => 'ok',
        'message' => '업로드 완료',

        'file'    => $fileName,
        'abs'     => $abs,
        'db_path' => $dbPath,
        'mime'    => $mime,
        'size'    => $size,
    ];
}


/* ---------------------------------------------------------------
 * 5. DB 경로 → 절대경로 변환
 * --------------------------------------------------------------- */
function storage_resolve_abs(string $dbPath): ?string
{
    $map = storage_bucket_map();

    foreach ($map as $bucket => $dir) {
        if (str_starts_with($dbPath, $bucket)) {

            $relative = substr($dbPath, strlen($bucket));
            $abs = rtrim($dir, '/') . '/' . ltrim($relative, '/');

            return is_file($abs) ? $abs : null;
        }
    }

    return null;
}


/* ---------------------------------------------------------------
 * 6. DB 경로 → URL 변환 (Public 전용)
 * --------------------------------------------------------------- */
function storage_to_url(string $dbPath): ?string
{
    if (!str_starts_with($dbPath, 'public://')) {
        storage_log()->warning("⚠ 잘못된 경로: public://로 시작하지 않음", ['dbPath' => $dbPath]);
        return null;
    }

    // ✅ 프록시/리버스프록시까지 고려한 scheme 판별
    $isHttps =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $scheme = $isHttps ? 'https://' : 'http://';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // ✅ 현재 앱이 이미 /public 아래에서 실행되는지 감지
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $publicPrefix = str_starts_with($scriptName, '/public/') ? '' : '/public';

    // public://brand/xxx.ico → /uploads/brand/xxx.ico
    $relative = str_replace('public://', '/uploads/', $dbPath);

    // 최종 URL
    $url = $scheme . $host . $publicPrefix . $relative;

    // 🔥 디버깅 로그 추가
    storage_log()->info("🌐 storage_to_url()", [
        'dbPath'        => $dbPath,
        'script_name'   => $scriptName,
        'public_prefix' => $publicPrefix,
        'url'           => $url,
    ]);

    return $url;
}



/* ---------------------------------------------------------------
 * 7. 파일 삭제
 * --------------------------------------------------------------- */
function storage_delete(string $dbPath): bool
{
    $abs = storage_resolve_abs($dbPath);

    if ($abs && is_file($abs)) {
        @unlink($abs);

        storage_log()->warning("🗑 파일 삭제됨", [
            'dbPath' => $dbPath,
            'abs'    => $abs,
        ]);

        return true;
    }

    storage_log()->warning("⚠ 삭제 실패 (파일 없음)", [
        'dbPath' => $dbPath,
    ]);

    return false;
}


/* ---------------------------------------------------------------
 * 시스템 전용 경로 (버킷 아님)
 * --------------------------------------------------------------- */
function storage_system_paths(): array
{
    return [
        'sessions'  => STORAGE_ROOT . '/sessions',
        'logs'      => STORAGE_ROOT . '/logs',
        'db_backup' => STORAGE_ROOT . '/db_backup',
    ];
}

function storage_system_path(string $key): ?string
{
    $paths = storage_system_paths();
    return $paths[$key] ?? null;
}
