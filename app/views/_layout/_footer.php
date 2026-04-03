<?php
// 경로: PROJECT_ROOT . '/app/views/_layout/_footer.php'
use Core\Helpers\ConfigHelper;
?>
<div class="container text-center">
    <?= htmlspecialchars(
        ConfigHelper::system(
            'footer_text',
            'ⓒ ' . date('Y') . ' SUKHYANG ERP. All rights reserved.'
        ),
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</div>

