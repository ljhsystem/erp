<main class="module-entry-page p-4">
    <div class="card">
        <div class="card-body">
            <h1 class="h5 mb-2"><?= htmlspecialchars($pageTitle ?? '대외기관업무', ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="text-muted mb-0">
                <?= !empty($isDashboard)
                    ? '소득자료를 기준으로 기관별 신고업무와 신고이력을 연결하는 대외기관업무 허브입니다.'
                    : '메뉴 구조가 연결되었습니다. 세부 업무 기능은 후속 단계에서 제공됩니다.' ?>
            </p>
        </div>
    </div>
</main>
