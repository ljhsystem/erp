<?php
// 경로: PROJECT_ROOT . '/app/views/layout/footer.php';
use Core\Helpers\AssetHelper;
use Core\Helpers\ConfigHelper;
?>
<!-- ✅ 고정형 푸터 -->
<?= AssetHelper::css('/assets/css/pages/layout/footer.css') ?>

<footer class="footer footer-fixed">
    <div class="container">
        <small>
            <?= htmlspecialchars(
                ConfigHelper::system(
                    'footer_text',
                    'ⓒ ' . date('Y') . ' SUKHYANG ERP. All rights reserved.'
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </small>
    </div>
</footer>

<!-- ✅ Bootstrap JS (페이지 하단에 유지) -->
<?= AssetHelper::js('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js') ?>