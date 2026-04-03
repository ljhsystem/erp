<?php
//경로: PROJECT_ROOT . '/app/views/home/about.php'
use Core\Helpers\AssetHelper;
// 1. 프로젝트 루트 상수 정의
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__, 3));
}

// 2. 공용 asset() 함수 로드
//require_once PROJECT_ROOT . '/core/Helpers/AssetHelper.php';
// ✅ undefined 방지: $images 기본값 설정
$images = $images ?? [];

// 3. 페이지 제목
$pageTitle = "About the company";

// 4. 본문 내용 버퍼링 시작
ob_start();
?>

<main class="flex-grow-1 intro-page">
  
  <!-- 로딩 애니메이션 -->
  <div id="loading" class="loading">
    <div class="spinner"></div>
  </div>

  <!-- 메인 콘텐츠 -->
  <div id="content" style="display: none;">
    
    <!-- 팝업 -->
    <div id="popup" class="popup" style="display: none;">
      <span class="popup-close">&times;</span>
      <div class="popup-content-container">
        <img class="popup-content" id="popup-img" alt="">
        <div id="popup-text" class="popup-text"></div>
      </div>
    </div>

    <!-- 타이틀 -->
    <div class="container intro-title">
      <h1 translate="no"><?= htmlentities($pageTitle, ENT_NOQUOTES, 'UTF-8', false) ?></h1>
      <p translate="no">만족하는 사람은 언제나 부자다.</p>
    </div>

    <!-- 포트폴리오 -->
    <section id="portfolio" class="portfolio py-3">
      <div class="container">

        <!-- 필터 버튼 -->
        <div class="gallery-filter mb-3 text-center">
          <button class="btn btn-outline-dark filter-button" data-filter="all" translate="no">All</button>
          <button class="btn btn-outline-dark filter-button" data-filter="before">
            <i class="bi bi-caret-left-square-fill"></i>
          </button>
          <button class="btn btn-outline-dark filter-button" id="yearButton1" data-filter=""></button>
          <button class="btn btn-outline-dark filter-button" id="yearButton2" data-filter=""></button>
        </div>

        <!-- 갤러리 -->
        <div class="gallery_product">
          <div class="js-masonry-list row" id="js-masonry-list">

          <?php foreach ($images as $image): ?>
            <div class="col-6 col-md-4 col-lg-3 js-masonry-elm filter"
                data-year="<?= htmlspecialchars($image['year']) ?>">

              <div class="img img-common"
                  style="background-image: url('<?= htmlspecialchars($image['url']) ?>');"
                  data-src="<?= htmlspecialchars($image['url']) ?>"
                  data-alt="<?= htmlspecialchars($image['alt']) ?>">
                <p><?= htmlspecialchars($image['title']) ?></p>
              </div>

            </div>
          <?php endforeach; ?>


          </div>
        </div>

      </div>
    </section>
  </div>

  <!-- 이미지 프리로드 -->
  <div style="display: none;">
    <?php foreach ($images as $image): ?>
      <img src="<?= htmlspecialchars($image['url']) ?>"
          alt="<?= htmlspecialchars($image['alt']) ?>">
    <?php endforeach; ?>
  </div>

</main>

<?php
// 5. 버퍼링 종료 → Layout에서 사용될 $content 변수 생성
$content = ob_get_clean();

// 6. 페이지 전용 CSS/JS 등록 (정답: /assets 사용)
$pageStyles = AssetHelper::css('/assets/css/pages/home/about.css');
//$pageStyles = AssetHelper::css('/assets/css/pages/_layout/spiner.css');
$pageScripts = AssetHelper::module('/assets/js/pages/home/about.js');
//$pageScripts = AssetHelper::js('/assets/js/pages/_layout/spiner.js');


// 7. 공용 레이아웃 적용
include PROJECT_ROOT . '/app/views/_layout/_layout.php';
