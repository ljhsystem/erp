<?php
// 경로: PROJECT_ROOT . '/core/Bootstrap.php'
//namespace Core;
use Core\LoggerFactory;

// ============================================================
// 1. Composer Autoload
// ============================================================
$autoload = PROJECT_ROOT . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    trigger_error("vendor/autoload.php 파일을 찾을 수 없습니다.", E_USER_ERROR);
}

// ============================================================
// 2. Helper / Functions 로드 (🔥 핵심 추가)
// ============================================================
foreach (glob(PROJECT_ROOT . '/core/Helpers/*.php') as $file) {
    require_once $file;
}

// ============================================================
// 3. Secret 로드
// ============================================================
$secretFile = realpath(PROJECT_ROOT . '/../secure-config/app_secret.php');

if ($secretFile && file_exists($secretFile)) {
    $secretConfig = require $secretFile;

    if (!empty($secretConfig['APP_SECRET'])) {
        define('APP_SECRET', $secretConfig['APP_SECRET']);
    } else {
        trigger_error("APP_SECRET 값이 없습니다.", E_USER_ERROR);
    }
} else {
    trigger_error("secure-config/app_secret.php 파일이 없습니다.", E_USER_ERROR);
}

// ============================================================
// 4. 에러 설정
// ============================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', PROJECT_ROOT . '/storage/logs/php_errors.log');
error_reporting(E_ALL);

// ============================================================
// 5. Logger 시작
// ============================================================
$logger = LoggerFactory::getLogger('core-Bootstrap');
$logger->info('Bootstrap initialized');





// <?php
// //  경로: PROJECT_ROOT . '/core/Bootstrap.php'
// //  Bootstrap: ERP 공통 초기화 (Router 실행 전 필수 로드)

// namespace Core;

// // 1. Composer Autoload
// $autoload = PROJECT_ROOT . '/vendor/autoload.php';
// if (file_exists($autoload)) {
//     require_once $autoload;
// }

// // 2. Core 파일 로드
// $coreFiles = [
//     '/core/LoggerFactory.php',
//     '/core/Database.php',
//     '/core/Storage.php',
// ];

// foreach ($coreFiles as $file) {
//     $path = PROJECT_ROOT . $file;
//     if (!file_exists($path)) {
//         trigger_error("Missing core file: {$path}", E_USER_ERROR);
//     }
//     require_once $path;
// }

// // 2-A. Helper 자동 로딩 (🔥 추가)
// $helperPath = PROJECT_ROOT . '/core/Helpers/*.php';

// foreach (glob($helperPath) as $helperFile) {
//     require_once $helperFile;
// }

// // 3. 네임스페이스 import
// use Core\LoggerFactory;

// // 3-A. APP_SECRET 글로벌 로드 (secure-config)
// $secretFile = realpath(PROJECT_ROOT . '/../secure-config/app_secret.php');

// if ($secretFile && file_exists($secretFile)) {
//     $secretConfig = require $secretFile;

//     if (!empty($secretConfig['APP_SECRET'])) {
//         define('APP_SECRET', $secretConfig['APP_SECRET']);
//     } else {
//         trigger_error("APP_SECRET 값이 secure-config/app_secret.php 에 없습니다.", E_USER_ERROR);
//     }
// } else {
//     trigger_error("secure-config/app_secret.php 파일을 찾을 수 없습니다.", E_USER_ERROR);
// }



// // 4. 오류 설정
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// ini_set('log_errors', 1);
// ini_set('error_log', PROJECT_ROOT . '/storage/logs/php_errors.log');
// error_reporting(E_ALL);
// //ini_set('display_errors', 1); // ❌ 브라우저에 에러 노출 금지
// //ini_set('log_errors', 1);     // ✅ 에러를 로그로 기록
// //ini_set('error_log', __DIR__ . '/../storage/logs/php_errors.log'); // 로그 파일 경로 설정
// //error_reporting(E_ALL);       // 모든 에러 기록
// // 에러 표시 설정 (개발용)
// //ini_set('display_errors', 1);
// //ini_set('log_errors', 1);
// //ini_set('display_startup_errors', 1);
// //error_reporting(E_ALL);

// // 5. Storage 디렉터리 생성
// //if (function_exists('storage_ensure_dirs')) {
// //    storage_ensure_dirs(true);
// //}

// // 6. 부팅 로그
// $logger = LoggerFactory::getLogger('core-Bootstrap(system_boot)');
// $logger->info('Bootstrap 초기화 완료');
// $authLogger = LoggerFactory::getLogger('core-Bootstrap(auth-register)');
// $authLogger->info('Auth register log initialized');



