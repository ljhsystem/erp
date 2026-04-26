<?php
// 경로: PROJECT_ROOT . '/app/views/layout/header.php'
use Core\Helpers\AssetHelper;
// layout.php에서 로드된 UI 설정 배열 사용
$ui = $uiSettings ?? [];

// 기본값 보정
$ui = array_merge([
    'ui_skin'        => 'default',
    'theme_mode'     => 'light',
    'font_family'    => '',
    'ui_density'     => 'normal',
    'font_scale'     => 'normal',
    'table_density'  => 'normal',
    'card_density'   => 'normal',
    'radius_style'   => 'rounded',
    'button_style'   => 'solid',
    'row_focus'      => 'normal',
    'link_underline' => 'off',
    'icon_scale'     => 'normal',
    'alert_style'    => 'normal',
    'motion_mode'    => 'on',
    'sidebar_default' => 'expanded',
], $ui);
?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle ?? '', ENT_QUOTES, 'UTF-8') ?></title>

    <!-- =====================================================
         UI 표시 설정: CSS 변수
    ====================================================== -->
    <style>
        :root {
            /* 기본 글꼴 */
            --font-base: <?= $ui['font_family']
                                ? "'" . htmlspecialchars($ui['font_family'], ENT_QUOTES, 'UTF-8') . "', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif"
                                : "system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif"
                            ?>;
            --color-primary: <?= htmlspecialchars((string)($ui['primary_color'] ?? '#0d6efd'), ENT_QUOTES, 'UTF-8') ?>;
            --color-primary-hover: color-mix(in srgb, var(--color-primary) 88%, black);
            --color-primary-soft: color-mix(in srgb, var(--color-primary) 14%, transparent);
            --color-sidebar-bg: <?= htmlspecialchars((string)($ui['sidebar_color'] ?? '#ffffff'), ENT_QUOTES, 'UTF-8') ?>;
            --color-text: <?= htmlspecialchars((string)($ui['text_color'] ?? '#212529'), ENT_QUOTES, 'UTF-8') ?>;
            --color-text-muted: color-mix(in srgb, var(--color-text) 58%, white);
            --color-sidebar-hover-bg: color-mix(in srgb, var(--color-primary) 6%, var(--color-sidebar-bg));
            --color-sidebar-active-bg: color-mix(in srgb, var(--color-primary) 14%, var(--color-sidebar-bg));
            --color-sidebar-border: color-mix(in srgb, var(--color-text) 12%, var(--color-sidebar-bg));
            --color-sidebar-icon: color-mix(in srgb, var(--color-text) 55%, white);
            --navbar-bg-start: color-mix(in srgb, var(--color-primary) 80%, black);
            --navbar-bg-end: var(--color-primary);
            --navbar-surface-soft: color-mix(in srgb, white 14%, transparent);
            --navbar-active-bg: color-mix(in srgb, white 20%, transparent);
        }
    </style>

    <!-- =====================================================
         UI 표시 설정: JS 전역 전달
    ====================================================== -->
    <script>
        window.__UI_SETTINGS__ = {
            skin: "<?= htmlspecialchars($ui['ui_skin'], ENT_QUOTES, 'UTF-8') ?>",
            theme: "<?= htmlspecialchars($ui['theme_mode'], ENT_QUOTES, 'UTF-8') ?>",
            uiDensity: "<?= htmlspecialchars($ui['ui_density'], ENT_QUOTES, 'UTF-8') ?>",
            fontScale: "<?= htmlspecialchars($ui['font_scale'], ENT_QUOTES, 'UTF-8') ?>",
            tableDensity: "<?= htmlspecialchars($ui['table_density'], ENT_QUOTES, 'UTF-8') ?>",
            cardDensity: "<?= htmlspecialchars($ui['card_density'], ENT_QUOTES, 'UTF-8') ?>",
            radiusStyle: "<?= htmlspecialchars($ui['radius_style'], ENT_QUOTES, 'UTF-8') ?>",
            buttonStyle: "<?= htmlspecialchars($ui['button_style'], ENT_QUOTES, 'UTF-8') ?>",
            rowFocus: "<?= htmlspecialchars($ui['row_focus'], ENT_QUOTES, 'UTF-8') ?>",
            linkUnderline: "<?= htmlspecialchars($ui['link_underline'], ENT_QUOTES, 'UTF-8') ?>",
            iconScale: "<?= htmlspecialchars($ui['icon_scale'], ENT_QUOTES, 'UTF-8') ?>",
            alertStyle: "<?= htmlspecialchars($ui['alert_style'], ENT_QUOTES, 'UTF-8') ?>",
            motionMode: "<?= htmlspecialchars($ui['motion_mode'], ENT_QUOTES, 'UTF-8') ?>",
            sidebarDefault: "<?= htmlspecialchars($ui['sidebar_default'], ENT_QUOTES, 'UTF-8') ?>"
        };
    </script>

    <!-- =====================================================
        Favicon
    ===================================================== -->
    <?php if (!empty($faviconUrl)): ?>
        <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($faviconUrl, ENT_QUOTES, 'UTF-8') ?>?v=<?= time() ?>">
    <?php endif; ?>

    <!-- 외부 CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?= AssetHelper::css('https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css') ?>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@100..900&display=swap" rel="stylesheet">

    <?= AssetHelper::css('https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css') ?>
    <?= AssetHelper::css('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css') ?>
    <?= AssetHelper::css('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css') ?>

    <?= AssetHelper::css('https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css') ?>
    <?= AssetHelper::css('https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css') ?>
    <?= AssetHelper::css('https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css') ?>
    <?= AssetHelper::css('https://cdn.datatables.net/rowreorder/1.4.1/css/rowReorder.dataTables.min.css') ?>
    <?= AssetHelper::css('https://cdn.datatables.net/fixedheader/3.4.0/css/fixedHeader.dataTables.min.css') ?>
    <?= AssetHelper::css('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css') ?><!-- select2기능 -->

    <?= AssetHelper::css('https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css') ?>
    <?= AssetHelper::css('https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css') ?>
    <?= AssetHelper::css('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css') ?>


    <!-- 공통 CSS -->
    <?= AssetHelper::css('/assets/css/common/picker.css') ?>
    <?= AssetHelper::css('/assets/css/common/notification.css') ?>
    <?= AssetHelper::css('/assets/css/pages/layout/spinner.css') ?>
    <?= AssetHelper::css('/assets/css/pages/layout/navbar.css') ?>
    <?= AssetHelper::css('/assets/css/pages/layout/footer.css') ?>
    <?= AssetHelper::css('/assets/css/pages/layout/layout.css') ?>
    <?= AssetHelper::css('/assets/css/components/data-table.css') ?>
    <?= AssetHelper::css('/assets/css/components/search-form.css') ?>
    <?= AssetHelper::css('/assets/css/components/excel-manager.css') ?>
    <?= AssetHelper::css('/assets/css/components/trash-manager.css') ?>


    <!-- 페이지별 CSS -->
    <?php if (!empty($pageStyles)) echo $pageStyles; ?>

    <!-- JS -->
    <?= AssetHelper::js('https://code.jquery.com/jquery-3.7.1.min.js') ?>
    <?= AssetHelper::js('https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js') ?><!-- Bootstrap -->
    <?= AssetHelper::js('https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js') ?><!-- 이미지지캡쳐기능 --><!-- 모달그대로캡쳐기능 --><!-- Utilities -->
    <?= AssetHelper::js('https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js') ?> <!-- DataTables Core -->
    <?= AssetHelper::js('https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js') ?><!-- DataTables Buttons -->
    <?= AssetHelper::js('https://cdn.datatables.net/rowreorder/1.4.1/js/dataTables.rowReorder.min.js') ?>
    <?= AssetHelper::js('https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js') ?><!-- Buttons 확장 -->
    <?= AssetHelper::js('https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js') ?>
    <?= AssetHelper::js('https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js') ?><!-- 🔥 이게 없어서 터진 것 -->
    <?= AssetHelper::js('https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js') ?><!-- 파일 출력용 -->
    <?= AssetHelper::js('https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js') ?>
    <?= AssetHelper::js('https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js') ?>
    <?= AssetHelper::js('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js') ?><!-- select2기능 -->
    <?= AssetHelper::js('https://t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js') ?><!-- 카카오 주소 API + 공통 주소 JS -->
    <?= AssetHelper::js('/assets/js/common/address.js') ?>
    <?= AssetHelper::js('/assets/js/common/notification.js') ?>
    <script type="module" src="<?= AssetHelper::url('/assets/js/common/picker/picker.select2.js') ?>"></script>
    <?= AssetHelper::js('/assets/js/common/file.js') ?><!-- 파일주소 JS -->
    <?= AssetHelper::js('/assets/js/common/esc-manager.js') ?><!-- esc JS -->



</head>
