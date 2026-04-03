<?php
// 경로: PROJECT_ROOT . '/app/views/home/vision.php'
use Core\Helpers\AssetHelper;
// 1. 프로젝트 루트 상수 정의
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__, 3));
}

// 2. 공용 asset() 함수 로드
//require_once PROJECT_ROOT . '/core/Helpers/AssetHelper.php';

// 3. 페이지 제목 설정
$pageTitle = "Future Vision";

// 4. 본문 콘텐츠 버퍼링 시작
ob_start();
?>

<main class="flex-grow-1 intro-page">

    <!-- 타이틀 -->
    <div class="container intro-title">
        <h1 translate="no"><?= htmlentities($pageTitle, ENT_NOQUOTES, 'UTF-8', false) ?></h1>
        <p translate="no">미래를 위해 지금 이 순간 최선을 다하자.</p>
    </div>

    <!-- 본문 내용 -->
    <div id="vision" class="container">
        <section style="margin-top: 0;">
            <h4>Welcome to Suk-hyang</h4>
            <h2 class="active">우리는&nbsp;<span></span></h2>
            <p>
                <span class="image blinking" translate="no">
                    Let’s do our best at this moment for the future.<br>
                    A contented man is always rich.<br><br>
                    Since_2013
                </span>
            </p>
        </section>
    </div>

</main>

<?php
// 5. 버퍼링 종료 → $content 저장
$content = ob_get_clean();

// 6. 페이지 전용 CSS/JS 등록 (정답: /assets 사용)
$pageStyles = AssetHelper::css('/assets/css/pages/home/vision.css');
$pageScripts = AssetHelper::js('/assets/js/pages/home/vision.js');

// 7. 공용 레이아웃 적용
include PROJECT_ROOT . '/app/views/_layout/_layout.php';
