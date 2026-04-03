<?php
// 경로: PROJECT_ROOT . '/app/views/home/index.php';
use Core\Helpers\AssetHelper;
use core\Helpers\ConfigHelper;

// 브라우저 페이지 제목
$pageTitle = ConfigHelper::system(
  'page_title',
  'SUKHYANG ERP'
);

ob_start();
?>

<div class="square-wrapper">
  <div class="square">
    <span></span>
    <span></span>
    <span></span>
    <div class="square-inner">

      <!-- 홈 인트로 제목 -->
      <h2>
        <?= htmlspecialchars(
          ConfigHelper::system(
            'home_intro_title',
            'SUKHYANG ERP'
          ),
          ENT_QUOTES,
          'UTF-8'
        ) ?>
      </h2>

      <!-- 홈 인트로 문구 -->
      <p>
        <?= nl2br(htmlspecialchars(
          ConfigHelper::system(
            'home_intro_description',
            '현장과 본사를 연결하는 통합 업무 관리 시스템입니다.'
          ),
          ENT_QUOTES,
          'UTF-8',
        )) ?>
      </p>


      <!-- 홈 인트로 이동 URL -->
      <?php
      $introUrl = ConfigHelper::system('home_intro_url', '');
      if (!empty($introUrl)):
      ?>
        <a href="<?= htmlspecialchars($introUrl, ENT_QUOTES, 'UTF-8') ?>"
          target="_blank"
          rel="noopener noreferrer">
          Read more
        </a>
      <?php endif; ?>



    </div>
  </div>
</div>

<?php
// 버퍼링 종료 → $content로 저장
$content = ob_get_clean();

// 페이지 전용 CSS/JS 등록  
$pageStyles = AssetHelper::css('/assets/css/pages/home/index.css');


// 공용 레이아웃 적용
include __DIR__ . '/../_layout/_layout.php';
