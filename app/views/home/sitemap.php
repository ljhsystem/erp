<?php
// 경로: PROJECT_ROOT . '/app/views/sitemap.php'
// 페이지 캐싱 방지
if (!headers_sent()) {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');
}
$userId = $_SESSION['user']['id'] ?? '';
$layoutOptions = [
    'header'  => true,
    'navbar'  => true,
    'sidebar' => false,
    'footer'  => true,
    'wrapper' => 'single'
];
$pageStyles  = $pageStyles  ?? '';
$pageScripts = $pageScripts ?? '';

/**
 * ▶ 새로운 사이트맵 데이터 (주신 구조 1:1 반영)
 * 섹션(왼쪽 큰 제목) > 그룹(카드 제목) > [라벨, 경로]
 * 경로는 추정값으로 매핑했습니다. 실제 파일명과 다르면 이 배열만 수정하세요.
 */
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
            ['문서대시보드', '/sukhyang'],
            ['문서등록', '/sukhyang/file_register'],
            ['문서상세보기', '/sukhyang/view'],
            ['문서수정', '/sukhyang/edit'],
            ['문서통계', '/sukhyang/stats'],
        ],
    ],

    '전자결재' => [
        '결재Ⅰ' => [
            ['결재목록', '/approval'],
            ['지출결의서작성', '/approval/write_expenditure'],
            ['구매요청서', '/approval/write_purchase_request'],            
            ['실행검토요청', '/approval/write_review_request'],
            ['기성검토요청', '/approval/write_progress_review'],           
        ],
        '결재Ⅱ' => [
            ['휴가신청서', '/approval/write_leave_request'],
            ['출장보고서', '/approval/write_trip_report'],
            ['업무보고서', '/approval/write_work_report'],
            ['외화송금결재', '/approval/write_foreign_remit'],
            ['자유양식기안서', '/approval/write_free_draft'],
            ['결재현황', '/approval/status'],
        ],
    ],

    '회계관리' => [
        '거래원장대시보드' => [
            ['대시보드', '/ledger'],
        ],
        '기초정보관리' => [
            ['회사정보', '/ledger/company'],
            ['거래처등록', '/ledger/client'],
            ['협력사평가', '/ledger/vendor_eval'],  // ✅ 신규 추가
            ['계정과목/적요', '/ledger/masters/accounts.php'],
            ['프로젝트등록', '/ledger/masters/projects.php'],
            ['업무용승용차', '/ledger/masters/vehicles.php'],
        ],
        '전표입력' => [
            ['일반전표입력', '/ledger/journal.php'],
            ['세금계산서입력', '/ledger/vat_invoice.php'],
            ['카드입력', '/ledger/card.php'],
            ['경비입력', '/ledger/expenses.php'],
            ['원천징수입력', '/ledger/withholding.php'],
            ['입출금입력', '/ledger/cashbook.php'],
            ['분개검토센터', '/ledger/journal_review.php'],
            ['분개흐름도', '/ledger/journal_flow.php'],
        ],
        '장부관리' => [
            ['일/월계표', '/ledger/reports/daily_monthly.php'],
            ['계정별원장', '/ledger/reports/account_ledger.php'],
            ['PJ계정별원장', '/ledger/reports/project_account.php'],
            ['거래처원장', '/ledger/reports/client_ledger.php'],
            ['총계정원장', '/ledger/general_ledger.php'],
            ['분개장', '/ledger/entries.php'],
            ['매입매출장', '/ledger/reports/purchase_sales.php'],
            ['운행기록부', '/ledger/reports/vehicle_log.php'],
        ],
        '결산/마감' => [
            ['월마감처리', '/ledger/closing/month_close.php'],
            ['기초잔액입력', '/ledger/closing/opening_balance.php'],
            ['이월처리', '/ledger/closing/carry_forward.php'],
        ],
        '결산/재무제표' => [
            ['결산자료입력', '/ledger/fs/input.php'],
            ['합계잔액시산표', '/ledger/trial_balance.php'],
            ['재무상태표', '/ledger/fs/balance_sheet.php'],
            ['손익계산서', '/ledger/fs/income_statement.php'],
            ['원가명세서', '/ledger/fs/cost_of_construction.php'],
            ['이익잉여금처분', '/ledger/fs/retained_earnings.php'],
            ['자본변동표', '/ledger/fs/changes_in_equity.php'],
            ['현금흐름표', '/ledger/fs/cash_flow.php'],
            ['운영/세무차이분석', '/ledger/analysis/op_vs_tax.php'],
        ],
        '신고' => [
            ['상용근로자신고', '/ledger/filing/regular_emp.php'],
            ['일용근로자신고', '/ledger/filing/daily_emp.php'],
            ['사업소득신고', '/ledger/filing/business_income.php'],
            ['부가세신고', '/ledger/filing/vat.php'],
            ['법인세신고', '/ledger/filing/corporate_tax.php'],
        ],
        '관리' => [
            ['법인등기관리', '/ledger/admin/corp_registry.php'],
            ['면허등록/관리', '/ledger/admin/licenses.php'],
            ['자산등록/관리', '/ledger/admin/assets.php'],
            ['감가상각분개', '/ledger/admin/depreciation_auto.php'],
            ['결재/결제현황', '/ledger/admin/approval_payment.php'],
            ['전표승인', '/ledger/admin/journal_approval.php'],
            ['사용자로그기록', '/ledger/admin/audit_log.php'],
            ['역할기반권한', '/ledger/admin/rbac.php'],
        ],
        '보고서/분석' => [
            ['PDF/엑셀 출력', '/ledger/analysis/export.php'],
            ['재무비율분석', '/ledger/analysis/ratios.php'],
            ['부서/PJ별손익', '/ledger/analysis/profit_by_dept_project.php'],
        ],
        '외부연동' => [
            ['홈택스/은행', '/ledger/integration/hometax_bank.php'],
            ['외화전표관리', '/ledger/integration/forex_journal.php'],
            ['REST API', '/ledger/integration/api.php'],
        ],
        '검색' => [
            ['전표검색', '/ledger/search.php'],
        ],
    ],

    '대외기관업무' => [
        '기관대시보드' => [
            ['기관대시보드', '/gov/index.php'],
        ],
        '대외기관Ⅰ' => [
            ['세무서(국세관련)', '/gov/nts.php'],
            ['지방자치단체(지방세관련)', '/gov/local_tax.php'],
            ['근로복지공단(보수총액/고용산재신고)', '/gov/klw.php'],                   
        ],
        '대외기관Ⅱ' => [     
            ['건강보험공단(건강보험신고)', '/gov/nhis.php'],
            ['국민연금관리공단(국민연금신고)', '/gov/nps.php'],   
            ['신용보증기금(신용보증관리)', '/gov/kodit.php'],
            
        ],
        '대외기관Ⅲ' => [            
            ['대한전문건설협회(실적신고)', '/gov/cak.php'],
            ['전문건설공제조합(보증/공제관리)', '/gov/union.php'],
            ['기술인협회(경력신고)', '/gov/engineer.php'],
            ['건설근로자공제회(퇴직공제부금신고)', '/gov/cwma.php'],
        ],



    ],

    '현장관리' => [
        '현장대시보드' => [
            ['현장대시보드', '/site/index.php'],           
        ],
        '프로젝트' => [            
            ['견적관리', '/site/estimate.php'],
            ['계약관리', '/site/contract.php'],
            ['실행관리', '/site/execution.php'],  
        ],
        '계약이행' => [           
            ['기성확정', '/site/progress_confirm.php'],   
            ['자재관리', '/site/materials.php'],             
            ['시공기성', '/site/progress_construction.php'],        
            ['노무관리', '/site/labor.php'],
            ['거래내역', '/site/transactions.php'],          
        ],
        '보증/공제' => [   
            ['보증/보험관리', '/site/bond_insurance.php'],            
        ],
        '안전' => [              
            ['안전관리', '/site/safety.php'],
        ],
    ],

    '공지/회의의' => [
        '공지' => [
            ['공지대시보드', '/notice/index.php'],
            ['직원별공지', '/notice/by_employee.php'],
            ['부서별공지', '/notice/by_dept.php'],
            ['전체공지', '/notice/all.php'],
        ],
        '회의' => [
            ['주간회의', '/meeting/weekly.php'],
            ['월간회의', '/meeting/monthly.php'],
            ['현장별공정회의', '/meeting/site.php'],            
        ],
    ],

    '기타' => [
        '도움말' => [
            ['사이트맵', '/sitemap'],
        ],
    ],
];
?>

<style>
/* ====== SITEMAP (Vivid Glass + Gradient) ====== */
/* 색상 팔레트 */
#sitemap-page{
  --bg:#ffffff;
  --text:#000;              /* ← 텍스트 기본색 블랙으로 고정 */
  --muted:#000;             /* ← 설명/보조 텍스트도 블랙 */
  --divider:#e6e7eb;

  --accent:#ff5a2f;
  --accent-2:#8a2eff;
  --accent-3:#14c8ff;

  --panel:#ffffffcc;
  --glow:#ff936e;
  --grad-1: linear-gradient(120deg, #ff7a18, #af002d 60%, #319197);
  --grad-2: linear-gradient(135deg, #8a2eff, #14c8ff);
  --grad-3: linear-gradient(135deg, #ff5a2f, #ffb86c);
}

/* 배경 */
#sitemap-page{
  position:relative;
  background: var(--bg);
  color:var(--text);
  min-height:calc(100vh - 120px);
  padding: 48px 20px 80px;
  overflow:hidden;
  margin-top: calc(-1 * var(--header-height)); /* 헤더만큼 위로 당김 */
  padding-top: var(--header-height);           /* 글자가 겹치지 않게 패딩만 확보 */
}

#sitemap-page::before{
  content:"";
  position:absolute; inset:0 0 auto 0; height:200px;
  background: var(--grad-1);
  filter: saturate(120%);
  opacity:.18;
  pointer-events:none;
  margin-top: 0px;
  
}
#sitemap-page::after{
  content:"";
  position:absolute; inset:0;
  background:
    radial-gradient(circle at 10% 10%, #00000007 0 2px, transparent 3px) 0 0/30px 30px,
    linear-gradient(transparent 49%, #00000005 50%, transparent 51%) 0 0/100% 48px;
  pointer-events:none;
}

/* 래퍼 */
.smap-wrap{ max-width: 1320px; margin:0 auto; position:relative; z-index:1; }

/* 헤더 */
.smap-head{ display:flex; align-items:flex-end; gap:16px; margin:0 0 32px; }
.smap-title{
  font-size:40px; font-weight:900; letter-spacing:-.02em; line-height:1.1;
  /* ← 그라데이션 제거, 블랙 고정 */
  color:#000 !important;
  background:none !important;
  -webkit-background-clip: initial !important;
  background-clip: initial !important;
  text-shadow:none !important;
}
.smap-desc{ color:#000; font-size:14px; padding-bottom:6px; }

/* 섹션 */
.smap-section{
  padding: 36px 0 20px;
  border-top: 1px solid var(--divider);
  display:grid; grid-template-columns: 260px 1fr; gap: 28px;
}
.smap-section:first-of-type{ border-top:0; padding-top: 8px; }

.smap-section-name{
  font-size:26px; font-weight:900; letter-spacing:-.01em;
  position:relative; padding-left:14px;
  color:#000; /* ← 블랙 */
}
.smap-section-name::before{
  content:""; position:absolute; left:0; top:.52em; width:8px; height:8px; border-radius:50%;
  background: var(--grad-3);
  box-shadow: 0 0 0 4px #ffefe7, 0 0 22px 2px #ffcbb3;
}

/* 그룹 그리드 */
.smap-groups{
  display:grid; gap:26px 28px;
  grid-template-columns: repeat(5, minmax(200px, 1fr));
}
@media (max-width:1200px){ .smap-groups{ grid-template-columns:repeat(3, minmax(200px,1fr)); } }
@media (max-width:768px){
  .smap-section{ grid-template-columns:1fr; }
  .smap-groups{ grid-template-columns:repeat(2, minmax(180px,1fr)); }
  .smap-section-name{ font-size:22px; }
  .smap-title{ font-size:32px; }
}

/* 카드 */
.smap-group{
  position:relative;
  padding: 18px 18px 14px;
  border-radius:16px;
  background: var(--panel);
  box-shadow:
    0 10px 24px rgba(0,0,0,.06),
    0 2px 6px rgba(0,0,0,.05),
    inset 0 1px 0 rgba(255,255,255,.55);
  -webkit-backdrop-filter: blur(8px);
  backdrop-filter: blur(8px);
  transition: transform .25s ease, box-shadow .25s ease;
  isolation:isolate;
  border: 1px solid #ffffffaa;
}
.smap-group::before{
  content:""; position:absolute; inset: -1px;
  border-radius:17px; z-index:-1;
  background: var(--grad-2);
  filter: blur(14px); opacity:.25;
  transition: opacity .3s ease, filter .3s ease;
}
.smap-group:hover{
  transform: translateY(-4px);
  box-shadow:
    0 16px 38px rgba(0,0,0,.08),
    0 4px 10px rgba(0,0,0,.06),
    0 0 0 1px rgba(255,255,255,.7) inset;
}
.smap-group:hover::before{ opacity:.45; filter: blur(10px); }

/* 카드 타이틀 */
.smap-group h4{
  font-size:18px; font-weight:900; margin:0 0 12px; letter-spacing:-.01em; position:relative; padding-bottom:8px;
  color:#000; /* ← 블랙 */
}
.smap-group h4::after{
  content:""; position:absolute; left:0; bottom:0; width:64px; height:3px;
  background: var(--grad-3); border-radius:999px;
  box-shadow: 0 0 10px #ffcaa6;
}

/* 링크 리스트 */
.smap-links{ list-style:none; margin:0; padding:0; }
.smap-links li{ margin:8px 0; }

.smap-links a{
  position:relative;
  color:#000 !important;    /* ← 링크 텍스트 블랙 */
  text-decoration:none;
  font-size:14px; font-weight:600;
  display:inline-flex; align-items:center; gap:8px;
  padding: 6px 8px 6px 10px;
  border-radius:10px;
  transition: color .15s ease, transform .15s ease, box-shadow .2s ease, background-color .2s ease;
  background: linear-gradient(#ffffff, #ffffff) padding-box,
              linear-gradient( to right, #e9eef7, #f6e9ff ) border-box;
  border: 1px solid transparent;
  box-shadow: 0 1px 0 rgba(0,0,0,.04);
}
.smap-links a::before{
  content:"";
  width:8px; height:8px; border-radius:50%;
  background: radial-gradient(circle at 30% 30%, #fff 0 30%, #ff7a18 60%, #af002d);
  box-shadow: 0 0 0 3px #fff, 0 0 10px rgba(255,90,47,.3);
}
.smap-links a:hover{
  color:#000 !important;    /* ← 호버 시에도 블랙 유지 */
  transform: translateX(3px);
  background: linear-gradient(#fff, #fff) padding-box,
              linear-gradient( to right, #ffd0c0, #e0d6ff ) border-box;
  box-shadow: 0 6px 16px rgba(255,149,128,.15), 0 2px 6px rgba(0,0,0,.06);
}
.smap-links a:active{ transform: translateX(2px) translateY(1px); }
.smap-links a::after{
  content:"↗";
  margin-left:4px;
  font-size:12px;
  opacity:.0;
  transform: translateY(1px);
  transition: opacity .15s ease;
}
.smap-links a:hover::after{ opacity:.65; }

/* 하단 브랜드 라벨 */
.smap-divider{ height:1px; background: var(--divider); margin:24px 0; }
.smap-brand{
  display:inline-flex; align-items:center; gap:8px;
  font-weight:900; font-size:13px;
  background: linear-gradient(90deg, #ffefe7, #eef8ff);
  color:#000 !important;    /* ← 블랙 */
  padding:8px 12px; border-radius:999px;
  border:1px solid #fff;
  box-shadow: 0 2px 8px rgba(0,0,0,.06), inset 0 1px 0 #fff;
}
.smap-brand::before{
  content:"★"; font-size:12px;
  color: transparent;
  background: var(--grad-2);
  -webkit-background-clip:text; background-clip:text;
}

/* 모션 최소화 */
@media (prefers-reduced-motion: reduce){
  .smap-group, .smap-links a{ transition:none !important; }
  .smap-group:hover{ transform:none; }
}
</style>




<main id="sitemap-page">
  <div class="smap-wrap">
    <div class="smap-head">
      <div class="smap-title">사이트맵</div>
      <div class="smap-desc">전체 섹션과 세부 메뉴를 한눈에 확인하세요.</div>
    </div>

    <?php foreach ($sitemap as $sectionName => $groups): ?>
      <section class="smap-section">
        <div class="smap-section-name"><?= htmlspecialchars($sectionName) ?></div>
        <div class="smap-groups">
          <?php foreach ($groups as $groupName => $links): ?>
            <div class="smap-group">
              <h4><?= htmlspecialchars($groupName) ?></h4>
              <ul class="smap-links">
                <?php foreach ($links as $item):
                  [$label, $href] = $item;
                  $isExternal = str_starts_with($href, 'http');
                ?>
                  <li>
                    <a href="<?= htmlspecialchars($href) ?>" <?= $isExternal ? 'target="_blank" rel="noopener"' : '' ?>>
                      <?= htmlspecialchars($label) ?>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>

    <div class="smap-divider"></div>
    <div class="smap-brand">CAK 스타일 • SUKHYANG ERP</div>
  </div>
</main>


