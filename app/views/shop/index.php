<?php
$pageTitle = $pageTitle ?? '쇼핑몰관리';
$shopTitle = $shopTitle ?? '쇼핑몰관리';
$shopDescription = $shopDescription ?? '쇼핑몰 모듈 준비 중입니다.';
$pageStyles = <<<'HTML'
<style>
.shop-module-page { max-width: 1180px; margin: 0 auto; }
.shop-module-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 20px; padding: 28px; box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08); }
.shop-module-title { margin: 0 0 10px; font-size: 30px; font-weight: 800; color: #0f172a; }
.shop-module-description { margin: 0; color: #64748b; line-height: 1.7; }
.shop-module-notice { margin-top: 20px; padding: 18px 20px; border-radius: 16px; background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; font-weight: 700; }
.shop-module-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-top: 24px; }
.shop-module-item { border: 1px solid #e5e7eb; border-radius: 16px; padding: 18px; background: #f8fafc; }
.shop-module-item h3 { margin: 0 0 8px; font-size: 17px; font-weight: 800; color: #0f172a; }
.shop-module-item p { margin: 0; color: #475569; line-height: 1.6; }
@media (max-width: 900px) {
    .shop-module-grid { grid-template-columns: 1fr; }
}
</style>
HTML;
?>

<main class="shop-module-page">
    <section class="shop-module-card">
        <h1 class="shop-module-title"><?= htmlspecialchars((string) $shopTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="shop-module-description"><?= htmlspecialchars((string) $shopDescription, ENT_QUOTES, 'UTF-8') ?></p>

        <div class="shop-module-notice">
            쇼핑몰 거래는 직접 입력하지 않고, 주문 및 결제 완료 흐름에서 공통 거래 데이터로 자동 생성되도록 설계합니다.
        </div>

        <div class="shop-module-grid">
            <article class="shop-module-item">
                <h3>주문 기반 구조</h3>
                <p>주문과 결제를 기준으로 거래를 자동 생성하는 쇼핑몰 모듈 진입 페이지입니다.</p>
            </article>
            <article class="shop-module-item">
                <h3>공통 거래 테이블</h3>
                <p>쇼핑몰도 별도 거래 테이블을 만들지 않고 `ledger_transactions`를 공통으로 사용합니다.</p>
            </article>
            <article class="shop-module-item">
                <h3>기본 work_unit</h3>
                <p>쇼핑몰에서 자동 생성되는 거래의 기본 단위는 `SHOP`으로 연결될 예정입니다.</p>
            </article>
        </div>
    </section>
</main>
