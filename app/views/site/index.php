<?php

$pageTitle = '현장대시보드';
$pageStyles = <<<'HTML'
<style>
.site-dashboard { padding: 24px; }
.site-dashboard-hero { background: linear-gradient(135deg, #0f766e, #155e75); color: #fff; border-radius: 20px; padding: 28px; box-shadow: 0 18px 40px rgba(15, 118, 110, 0.24); }
.site-dashboard-hero h1 { margin: 0 0 10px; font-size: 30px; }
.site-dashboard-hero p { margin: 0; opacity: 0.92; }
.site-dashboard-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-top: 20px; }
.site-dashboard-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 18px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06); }
.site-dashboard-card h3 { margin: 0 0 10px; font-size: 18px; }
.site-dashboard-card p { margin: 0; color: #4b5563; line-height: 1.5; }
.site-dashboard-link { display: inline-flex; margin-top: 14px; padding: 10px 14px; border-radius: 10px; background: #0f766e; color: #fff; text-decoration: none; }
@media (max-width: 1024px) {
    .site-dashboard-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 640px) {
    .site-dashboard-grid { grid-template-columns: 1fr; }
}
</style>
HTML;
?>

<main class="site-dashboard">
    <section class="site-dashboard-hero">
        <h1>현장대시보드</h1>
        <p>현장관리 영역의 주요 기능으로 빠르게 이동할 수 있는 임시 대시보드입니다.</p>
    </section>

    <section class="site-dashboard-grid">
        <article class="site-dashboard-card">
            <h3>거래내역</h3>
            <p>현장 직원이 입력한 거래 데이터를 조회하고 수정할 수 있습니다.</p>
            <a class="site-dashboard-link" href="/site/entry/create">거래입력 열기</a>
        </article>

        <article class="site-dashboard-card">
            <h3>거래입력</h3>
            <p>신규 거래를 등록하고 항목별 금액을 저장할 수 있습니다.</p>
            <a class="site-dashboard-link" href="/site/entry/create">거래 입력하기</a>
        </article>

        <article class="site-dashboard-card">
            <h3>현장 안내</h3>
            <p>이 화면은 `/site` 기본 진입점이며, 이후 현장관리 메뉴 확장 기준점으로 사용할 수 있습니다.</p>
        </article>

        <article class="site-dashboard-card">
            <h3>후속 확장</h3>
            <p>거래 외 기능이 추가되더라도 동일한 현장관리 허브로 연결할 수 있도록 구성했습니다.</p>
        </article>
    </section>
</main>
