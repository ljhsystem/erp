<?php
// Path: PROJECT_ROOT . '/app/views/layout/footer.php'
use Core\Helpers\AssetHelper;
use Core\Helpers\ConfigHelper;

$footerText = trim((string) ConfigHelper::system(
    'footer_text',
    '(c) ' . date('Y') . ' SUKHYANG ERP. All rights reserved.'
));

if ($footerText === '') {
    $footerText = '(c) ' . date('Y') . ' SUKHYANG ERP. All rights reserved.';
}
?>
<?= AssetHelper::css('/assets/css/pages/layout/footer.css') ?>

<footer class="footer footer-fixed">
    <div class="container">
        <small><?= htmlspecialchars($footerText, ENT_QUOTES, 'UTF-8') ?></small>
    </div>
</footer>

<?= AssetHelper::js('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js') ?>
