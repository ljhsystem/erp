<?php
// 경로: PROJECT_ROOT . '/app/views/layout/navbar.php'
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary top-nav fixed-top">
    <div class="container-fluid">

        <a class="navbar-brand d-flex align-items-center me-4" href="/dashboard">
            <?php if (!empty($mainLogoUrl)): ?>
                <img src="<?= htmlspecialchars($mainLogoUrl) ?>" alt="SUKHYANG Logo" class="navbar-logo me-2">
                <span class="fw-bold text-white">SUKHYANG ERP</span>
            <?php else: ?>
                <span class="fw-bold text-white">SUKHYANG ERP</span>
            <?php endif; ?>
        </a>


        <div class="collapse navbar-collapse me-auto">
            <ul class="navbar-nav flex-row">
                <li class="nav-item me-3"><a class="nav-link" href="/sukhyang">문서관리</a></li>
                <li class="nav-item me-3"><a class="nav-link" href="/approval">전자결재</a></li>
                <li class="nav-item me-3"><a class="nav-link" href="/ledger">회계관리</a></li>
                <li class="nav-item me-3"><a class="nav-link" href="/institution">대외기관업무</a></li>
                <li class="nav-item me-3"><a class="nav-link" href="/site">현장관리</a></li>
                <li class="nav-item me-3"><a class="nav-link" href="/notice">공지/회의</a></li>
                <li class="nav-item me-3"><a class="nav-link" href="/sitemap">사이트맵</a></li>
            </ul>
        </div>
        <div class="d-flex align-items-center">

            <!-- 상태 패널: 시계 아이콘 + 짧은 시간 표시, hover 시 전체 시간은 Bootstrap tooltip으로 표시 -->
            <div class="d-flex align-items-center text-white px-3 py-1 me-3" role="group" a-label="상태 패널">
                <i class="bi bi-clock me-2 text-white" aria-hidden="true"></i>
                <!-- 클릭 시 미니달력 열기: role/button, tabindex, aria-controls 추가 -->
                <span id="current-time"
                    data-format="tooltip"
                    data-bs-toggle="tooltip"
                    class="me-3 fs-6 text-white"
                    role="button"
                    tabindex="0"
                    aria-haspopup="dialog"
                    aria-controls="mini-calendar"
                    style="white-space:nowrap; cursor:pointer;">--:--</span>

                <a href="#" class="d-flex align-items-center text-white text-decoration-none me-3" role="button" aria-label="내 정보">
                    <i class="bi bi-person-workspace fs-5 me-2" role="img" aria-label="사용자 아이콘"></i>
                    <?php
                    // 안전한 표시: employee_name이 비어있으면 username 또는 기존 name으로 폴백
                    $sessUser = $_SESSION['user'] ?? [];
                    $emp = trim((string)($sessUser['employee_name'] ?? ''));
                    $uname = trim((string)($sessUser['username'] ?? ''));
                    $fallback = trim((string)($employee_name ?? $name ?? ''));
                    if ($emp !== '') {
                        $displayName = $emp;
                    } elseif ($uname !== '') {
                        $displayName = $uname;
                    } elseif ($fallback !== '') {
                        $displayName = $fallback;
                    } else {
                        $displayName = 'Guest';
                    }
                    ?>
                    <span class="fs-6"><?php echo htmlspecialchars($displayName); ?> 님</span>
                </a>

                <span id="session-timer" class="fs-6" data-expire-time="<?= htmlspecialchars($expireTime ?? 0) ?>" data-session-timeout="<?= htmlspecialchars($sessionTimeout ?? 0) ?>">00:00</span>
            </div>




            <!-- 미니 달력 컨테이너: layout.js가 위치/내용을 제어 -->
            <div id="mini-calendar" class="mini-calendar d-none" aria-hidden="true"></div>

            <audio id="session-alert-sound" src="/public/assets/sounds/<?= htmlspecialchars($sessionSound) ?>" preload="auto"></audio>
            <button class="btn btn-outline-light btn-sm me-2" onclick="extendSession()">연장</button>
            <a href="/profile" class="btn btn-outline-light btn-sm me-2">내정보</a>
            <a href="/logout" class="btn btn-outline-light btn-sm" onclick="logoutWithPopupClose()">로그아웃</a>
        </div>
    </div>
</nav>

<div class="fixed-top-space"></div>