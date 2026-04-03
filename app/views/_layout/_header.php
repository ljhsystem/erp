<?php
// 경로: PROJECT_ROOT . '/app/views/_layout/_header.php'
use Core\Helpers\AssetHelper;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
	<meta charset="UTF-8">
	<title><?= htmlspecialchars($pageTitle) ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<!-- 파비콘 -->
	<!-- <link rel="icon" href="<//?= AssetHelper::url('/favicon.ico') ?>"> -->
	<link rel="icon" href="/favicon.ico">

	<!-- preconnect (그대로 유지) -->
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

	<!-- 폰트 -->
	<?= AssetHelper::css('https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css') ?>
	<?= AssetHelper::css('https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@100..900&display=swap') ?>

	<!-- Bootstrap -->
	<?= AssetHelper::css('https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css') ?>
	<?= AssetHelper::css('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css') ?>

	<!-- 공통 레이아웃 CSS -->
	<?= AssetHelper::css('/assets/css/pages/_layout/logo.css') ?>
	<?= AssetHelper::css('/assets/css/pages/_layout/_navbar.css') ?>
	<?= AssetHelper::css('/assets/css/pages/_layout/_footer.css') ?>
	<?= AssetHelper::css('/assets/css/pages/_layout/_layout.css') ?>

	<!-- 페이지별 CSS -->
	<?= $pageStyles ?? '' ?>
</head>