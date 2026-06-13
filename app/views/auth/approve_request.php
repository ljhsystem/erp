<?php
declare(strict_types=1);

use Core\Helpers\AssetHelper;
use Core\Helpers\ConfigHelper;

$approveToken = isset($approveToken) ? (string) $approveToken : '';
$message = isset($message) ? (string) $message : '';
$formattedDate = isset($formattedDate) ? (string) $formattedDate : '';
$user = isset($user) && is_array($user) ? $user : null;
$baseUrl = rtrim((string) ConfigHelper::get('App.BaseUrl', ''), '/');
$homeUrl = $baseUrl === '' ? '/' : $baseUrl;
$pageTitle = "\u{D68C}\u{C6D0} \u{C2B9}\u{C778} \u{C694}\u{CCAD}";
$profileAlt = "\u{D504}\u{B85C}\u{D544}";
$emptyNameText = "\u{C774}\u{B984} \u{C5C6}\u{C74C}";
$usernameLabel = "\u{C544}\u{C774}\u{B514}:";
$createdAtLabel = "\u{AC00}\u{C785}\u{C77C}:";
$approveButtonText = "\u{C2B9}\u{C778}\u{D558}\u{AE30}";
$approvedButtonText = "\u{C774}\u{BBF8} \u{C2B9}\u{C778}\u{B428}";
$homeButtonText = "\u{BA54}\u{C778}\u{C73C}\u{B85C} \u{AC00}\u{AE30}";
$closeButtonText = "\u{CC3D} \u{B2EB}\u{AE30}";
$missingUserText = "\u{C0AC}\u{C6A9}\u{C790} \u{C815}\u{BCF4}\u{B97C} \u{CC3E}\u{C744} \u{C218} \u{C5C6}\u{C2B5}\u{B2C8}\u{B2E4}.";
$homeText = "\u{BA54}\u{C778}\u{C73C}\u{B85C}";
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?></title>
    <?= AssetHelper::css('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css') ?>
    <?= AssetHelper::css('/assets/css/pages/auth/approve_request.css') ?>
</head>
<body class="approve-request-page">
    <div class="card approve-request-card text-center">
        <div class="card-header"><?= $pageTitle ?></div>
        <div class="card-body p-4">
            <?php if ($user): ?>
                <div class="alert <?= $user['approved'] === 1 ? 'alert-info' : 'alert-warning' ?> mb-3">
                    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                </div>

                <?php if (!empty($user['profile_image'])): ?>
                    <img src="<?= htmlspecialchars((string) $user['profile_image'], ENT_QUOTES, 'UTF-8') ?>" class="profile-img" alt="<?= $profileAlt ?>">
                <?php endif; ?>

                <h5 class="mb-2"><strong><?= htmlspecialchars((string) ($user['employee_name'] ?: $emptyNameText), ENT_QUOTES, 'UTF-8') ?></strong></h5>
                <p class="text-muted mb-2"><?= $usernameLabel ?> <?= htmlspecialchars((string) $user['username'], ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-muted"><?= $createdAtLabel ?> <?= htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8') ?></p>

                <form method="post" action="<?= rtrim((string) ConfigHelper::get('App.BaseUrl', ''), '/') . '/api/auth/approval/approve' ?>" class="mt-4">
                    <input type="hidden" name="approve_token" value="<?= htmlspecialchars($approveToken, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="d-grid gap-2">
                        <?php if ($user['approved'] === 0): ?>
                            <button type="submit" class="btn btn-success"><?= $approveButtonText ?></button>
                        <?php else: ?>
                            <button type="button" class="btn btn-success" disabled><?= $approvedButtonText ?></button>
                        <?php endif; ?>
                        <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary"><?= $homeButtonText ?></a>
                        <button type="button" class="btn btn-outline-danger" onclick="window.close()"><?= $closeButtonText ?></button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-danger mb-3"><?= htmlspecialchars($message ?: $missingUserText, ENT_QUOTES, 'UTF-8') ?></div>
                <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary"><?= $homeText ?></a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
