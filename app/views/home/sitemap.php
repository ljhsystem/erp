<?php
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$layoutOptions = [
    'header' => true,
    'navbar' => true,
    'sidebar' => false,
    'footer' => true,
    'wrapper' => 'single',
];
$pageStyles = $pageStyles ?? '';
$pageScripts = $pageScripts ?? '';

$sitemap = [
    '대시보드' => [
        '대시보드' => [
            ['대시보드', '/dashboard'],
            ['통합보고서', '/dashboard/report'],
            ['일정/캘린더', '/dashboard/calendar'],
            ['최근활동', '/dashboard/activity'],
            ['공지사항', '/dashboard/notice'],
            ['실적현황', '/dashboard/kpi'],
        ],
        '설정' => [
            ['직원관리', '/dashboard/settings?tab=employee'],
            ['커버사진관리', '/dashboard/settings?tab=cover'],
            ['세션관리', '/dashboard/settings?tab=session'],
        ],
    ],
    '문서관리' => [
        '문서' => [
            ['문서대시보드', '/document'],
            ['문서등록', '/document/file_register'],
            ['문서상세보기', '/document/view'],
            ['문서수정', '/document/edit'],
            ['문서통계', '/document/stats'],
        ],
    ],
    '전자결재' => [
        '결재 1' => [
            ['결재목록', '/approval'],
            ['지출결의서작성', '/approval/write_expenditure'],
            ['구매요청서', '/approval/write_purchase_request'],
            ['실행검토요청', '/approval/write_review_request'],
            ['기성검토요청', '/approval/write_progress_review'],
        ],
        '결재 2' => [
            ['휴가요청서', '/approval/write_leave_request'],
            ['출장보고서', '/approval/write_trip_report'],
            ['업무보고서', '/approval/write_work_report'],
            ['외화송금결재', '/approval/write_foreign_remit'],
            ['자유양식기안문', '/approval/write_free_draft'],
            ['결재현황', '/approval/status'],
        ],
    ],
    '기타' => [
        '페이지' => [
            ['사이트맵', '/sitemap'],
        ],
    ],
];
?>

<style>
#sitemap-page {
  background: #ffffff;
  color: #111827;
  min-height: calc(100vh - 120px);
  padding: 48px 20px 80px;
}

.smap-wrap {
  max-width: 1320px;
  margin: 0 auto;
}

.smap-head {
  display: flex;
  align-items: end;
  gap: 16px;
  margin-bottom: 32px;
}

.smap-title {
  font-size: 40px;
  font-weight: 800;
}

.smap-desc {
  color: #6b7280;
  padding-bottom: 8px;
}

.smap-section {
  padding: 36px 0 20px;
  border-top: 1px solid #e5e7eb;
  display: grid;
  grid-template-columns: 240px 1fr;
  gap: 28px;
}

.smap-groups {
  display: grid;
  gap: 24px;
  grid-template-columns: repeat(4, minmax(200px, 1fr));
}

.smap-group {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  padding: 18px;
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
}

.smap-group h4 {
  margin: 0 0 12px;
  font-size: 18px;
}

.smap-links {
  list-style: none;
  margin: 0;
  padding: 0;
}

.smap-links li + li {
  margin-top: 8px;
}

.smap-links a {
  color: #1d4ed8;
  text-decoration: none;
}

@media (max-width: 960px) {
  .smap-section {
    grid-template-columns: 1fr;
  }

  .smap-groups {
    grid-template-columns: repeat(2, minmax(180px, 1fr));
  }
}
</style>

<main id="sitemap-page">
  <div class="smap-wrap">
    <div class="smap-head">
      <div class="smap-title">사이트맵</div>
      <div class="smap-desc">전체 섹션과 세부 메뉴를 확인하세요.</div>
    </div>

    <?php foreach ($sitemap as $sectionName => $groups): ?>
      <section class="smap-section">
        <div class="smap-section-name"><?= htmlspecialchars($sectionName, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="smap-groups">
          <?php foreach ($groups as $groupName => $links): ?>
            <div class="smap-group">
              <h4><?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?></h4>
              <ul class="smap-links">
                <?php foreach ($links as $item): ?>
                  <?php [$label, $href] = $item; ?>
                  <li><a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
</main>
