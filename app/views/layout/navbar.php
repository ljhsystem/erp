<?php
$displayName = $displayName ?? 'Guest';
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$navItems = [
    ['/dashboard', '메인'],
    ['/sukhyang', '문서관리'],
    ['/approval', '전자결재'],
    ['/ledger', '회계관리'],
    ['/institution', '대외기관업무'],
    ['/site', '현장관리'],
    ['/notice', '공지/회의'],
    ['/sitemap', '사이트맵'],
];

$isActiveNav = static function (string $href) use ($currentPath): bool {
    if ($href === '/') {
        return $currentPath === '/';
    }

    return $currentPath === $href || str_starts_with($currentPath, $href . '/');
};
?>
<nav class="top-nav fixed-top" aria-label="주요 네비게이션">
    <div class="container-fluid top-nav-shell">
        <div class="top-nav-primary">
            <button
                type="button"
                class="mobile-nav-toggle"
                id="mobile-nav-toggle"
                aria-label="메뉴 열기"
                aria-controls="mobile-nav-drawer"
                aria-expanded="false"
            >
                <span></span>
                <span></span>
                <span></span>
            </button>

            <a class="navbar-brand top-nav-brand" href="/dashboard">
                <?php if (!empty($mainLogoUrl)): ?>
                    <img src="<?= htmlspecialchars((string)$mainLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="SUKHYANG Logo" class="navbar-logo">
                <?php endif; ?>
                <span class="top-nav-brand-text">SUKHYANG ERP</span>
            </a>

            <div class="desktop-navbar" aria-label="데스크톱 메뉴">
                <ul class="desktop-navbar-menu">
                    <?php foreach ($navItems as [$href, $label]): ?>
                        <li class="desktop-navbar-item">
                            <a
                                class="nav-link<?= $isActiveNav($href) ? ' active' : '' ?>"
                                href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="desktop-navbar-meta">
            <div class="desktop-navbar-status" role="group" aria-label="상태 정보">
                <i class="bi bi-clock" aria-hidden="true"></i>
                <span
                    id="current-time"
                    data-format="tooltip"
                    data-bs-toggle="tooltip"
                    role="button"
                    tabindex="0"
                    aria-haspopup="dialog"
                    aria-controls="mini-calendar"
                >--:--</span>

                <a href="/profile" class="desktop-user-link">
                    <i class="bi bi-person-workspace" aria-hidden="true"></i>
                    <span class="user-name"><?= htmlspecialchars((string)$displayName, ENT_QUOTES, 'UTF-8') ?></span>
                </a>

                <span
                    id="session-timer"
                    data-expire-time="<?= htmlspecialchars((string)($expireTime ?? 0), ENT_QUOTES, 'UTF-8') ?>"
                    data-session-timeout="<?= htmlspecialchars((string)($sessionTimeout ?? 0), ENT_QUOTES, 'UTF-8') ?>"
                >00:00</span>
            </div>

            <div class="desktop-navbar-actions">
                <button type="button" class="btn btn-outline-light btn-sm" onclick="extendSession()">연장</button>
                <a href="/profile" class="btn btn-outline-light btn-sm">내정보</a>
                <a href="/logout" class="btn btn-outline-light btn-sm" onclick="logoutWithPopupClose()">로그아웃</a>
            </div>
        </div>
    </div>
</nav>

<div id="mini-calendar" class="mini-calendar d-none" aria-hidden="true"></div>
<audio id="session-alert-sound" src="/public/assets/sounds/<?= htmlspecialchars((string)$sessionSound, ENT_QUOTES, 'UTF-8') ?>" preload="auto"></audio>

<div class="mobile-nav-overlay" id="mobile-nav-overlay" hidden></div>

<aside class="mobile-navbar" id="mobile-nav-drawer" aria-label="모바일 메뉴" aria-hidden="true">
    <div class="mobile-navbar-head">
        <div class="mobile-navbar-brand">
            <?php if (!empty($mainLogoUrl)): ?>
                <img src="<?= htmlspecialchars((string)$mainLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="SUKHYANG Logo" class="navbar-logo">
            <?php endif; ?>
            <div>
                <strong>SUKHYANG ERP</strong>
                <p><?= htmlspecialchars((string)$displayName, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>

        <button type="button" class="mobile-nav-close" id="mobile-nav-close" aria-label="메뉴 닫기">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>

    <div class="mobile-navbar-status">
        <div class="mobile-status-chip">
            <i class="bi bi-clock" aria-hidden="true"></i>
            <span id="mobile-current-time">--:--</span>
        </div>
        <div class="mobile-status-chip">
            <i class="bi bi-hourglass-split" aria-hidden="true"></i>
            <span id="mobile-session-timer">00:00</span>
        </div>
    </div>

    <div class="mobile-navbar-body">
        <ul class="mobile-navbar-menu">
            <?php foreach ($navItems as [$href, $label]): ?>
                <li>
                    <a
                        class="mobile-nav-link<?= $isActiveNav($href) ? ' active' : '' ?>"
                        href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
                        data-mobile-nav-link="true"
                    >
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="mobile-navbar-user">
        <a href="/profile" class="mobile-user-link" data-mobile-nav-link="true">
            <i class="bi bi-person-circle" aria-hidden="true"></i>
            <span>내정보</span>
        </a>
        <button type="button" class="mobile-user-link" onclick="extendSession()">
            <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
            <span>세션 연장</span>
        </button>
        <a href="/logout" class="mobile-user-link mobile-logout-link" data-mobile-nav-link="true" onclick="logoutWithPopupClose()">
            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
            <span>로그아웃</span>
        </a>
    </div>
</aside>

<div class="fixed-top-space"></div>
