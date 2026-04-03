<?php
// 경로: PROJECT_ROOT . '/app/views/layout/breadcrumb.php';

/**
 * /app/views/layout/breadcrumb.php  (FULL, fixed 기본)
 *
 * 사용 예)
 *   $breadcrumb = ['거래원장'=>'/ledger','거래처등록'=>null];
 *   // 옵션 예: $breadcrumb_opts = ['fixed'=>false,'top'=>0,'right'=>0];
 *   include_once(__DIR__.'/breadcrumb.php');
 */

$opts  = $breadcrumb_opts ?? [];
$fixed = isset($opts['fixed']) ? (bool)$opts['fixed'] : false;
$top   = isset($opts['top'])   ? (int)$opts['top']   : 1;
$right = isset($opts['right']) ? (int)$opts['right'] : 20;

if (!isset($breadcrumb) || !is_array($breadcrumb) || empty($breadcrumb)) {
    // === 자동 생성 ===
    $uri   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
    $parts = array_values(array_filter(explode('/', trim($uri, '/'))));

    $labelMap = [
        '' => '홈',
        'dashboard'=>'대시보드','report'=>'통합보고서','calendar'=>'일정캘린더','activity'=>'최근활동',
        'notifications'=>'공지사항','kpi'=>'실적현황','settings'=>'설정',
        'sukhyang'=>'석향서류','approval'=>'결재서류',
        'ledger'=>'거래원장','institution'=>'기관업무',
        'site'=>'현장관리','notice'=>'공지사항','sitemap'=>'사이트맵',

        'employee'=>'직원관리','cover'=>'커버사진관리','session'=>'세션관리',

        'file_register'=>'문서등록','view'=>'문서상세보기','edit'=>'문서수정','stats'=>'문서통계',

        'write_expenditure'=>'지출결의서작성','write_purchase_request'=>'구매요청서',
        'write_leave_request'=>'휴가신청서','write_trip_report'=>'출장보고서','write_work_report'=>'업무보고서',
        'write_review_request'=>'실행검토요청','write_progress_review'=>'기성검토요청',
        'write_foreign_remit'=>'외화송금결재요청','write_free_draft'=>'자유양식기안서','status'=>'결재현황',

        'client'=>'거래처등록','account'=>'계정과목/적요등록','project'=>'프로젝트등록','car'=>'업무용승용차등록',
        'input'=>'전표입력','tax_invoice'=>'세금계산서입력','withholding'=>'원천징수입력','bank'=>'입출금입력',
        'card'=>'카드입력','corporate'=>'법인카드','personal'=>'개인카드',
        'book'=>'장부관리','daily_monthly'=>'일/월계표','project_account'=>'프로젝트계정별원장',
        'partner'=>'거래처원장','general'=>'총계정원장','journal'=>'분개장','purchase_sale'=>'매입매출장','car_log'=>'운행기록부',

        'closing'=>'결산/마감','monthly'=>'월마감처리','setup'=>'설정','opening_balance'=>'기초잔액입력','carry_forward'=>'이월처리',
        'data'=>'결산자료입력','trial_balance'=>'합계잔액시산표','balance_sheet'=>'재무상태표',
        'income_statement'=>'손익계산서','cost_statement'=>'원가명세서',
        'retained_earnings'=>'이익잉여금처분','equity_change'=>'자본변동표','cash_flow'=>'현금흐름표',

        'report'=>'보고서/신고','regular_worker'=>'상용근로자신고','daily_worker'=>'일용근로자신고',
        'business_income'=>'사업소득신고','vat'=>'부가세신고','corporate_tax'=>'법인세신고',

        'manage'=>'관리','payment'=>'결재/결제현황',
        'logs'=>'로그','user_activity'=>'사용자로그기록',
        'admin'=>'관리자','role_access'=>'역할기반권한설정',
        'assets'=>'자산등록/관리','license'=>'면허등록/관리','depreciation'=>'감가상각자동분개',

        'export'=>'PDF/엑셀 출력','finance_kpi'=>'재무비율분석','costcenter_pl'=>'부서/프로젝트별손익',
        'integration'=>'외부연동','data_fetch'=>'홈택스/기업은행연동','forex'=>'외화전표관리','api'=>'REST API','docs'=>'문서',
        'search'=>'전표검색',

        'tax_office'=>'세무서(국세관련)','local_government'=>'지방자치단체(지방세관련)',
        'welfare_corp'=>'근로복지공단(보수총액/고용산재신고)','health_insurance'=>'건강보험공단(건강보험신고)',
        'pension'=>'국민연금관리공단(국민연금신고)','credit_guarantee'=>'신용보증기금(신용보증관리)',
        'construction_assoc'=>'대한전문건설협회(실적신고)','construction_union'=>'전문건설공제조합(보증/공제관리)',
        'engineer_assoc'=>'기술인협회(경력신고)','construction_worker_union'=>'건설근로자공제회(퇴직공제부금신고)',

        'estimate'=>'견적관리','contract'=>'계약관리','execution'=>'실행관리','guarantee'=>'보증/보험관리',
        'progress'=>'기성확정내역','construction_progress'=>'시공기성확정내역','transaction'=>'거래내역','safety'=>'안전관리',
        'employee'=>'직원별공지','department'=>'부서별공지','all'=>'전체공지',
    ];

    $acc   = '';
    $built = [];
    $built['홈'] = empty($parts) ? null : '/';

    foreach ($parts as $i => $p) {
        $acc   .= '/'.$p;
        $isLast = ($i === count($parts)-1);
        $key    = $labelMap[$p] ?? $labelMap[basename($p)] ?? $p;
        $built[$key] = $isLast ? null : $acc;
    }
    $breadcrumb = $built;
}

if (!function_exists('e')) {
  function e($s){ 
      return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); 
  }
}


if (!defined('__BREADCRUMB_STYLE_ONCE__')) {
    define('__BREADCRUMB_STYLE_ONCE__', true); ?>
<style>
.breadcrumb-anchor{ position:relative; display:block; width:100%; min-height:0; }
.breadcrumb-nav{
  position:absolute; top:0; right:0; z-index:3000;
  font-size:13px; line-height:1; max-width:min(70vw,1200px);
}
.breadcrumb-list{
  display:inline-flex; align-items:center; gap:8px;
  margin:0; padding:8px 12px; border-radius:999px;
  background: rgba(255,255,255,.92); border:1px solid rgba(0,0,0,.06);
  box-shadow: 0 6px 18px rgba(0,0,0,.06), inset 0 1px 0 rgba(255,255,255,.6);
  -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px);
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.breadcrumb-item{ display:inline-flex; align-items:center; gap:8px; color:#6b7280; min-width:0; }
.breadcrumb-item + .breadcrumb-item::before{ content:"›"; color:#a1a1aa; margin:0 2px; }
.breadcrumb-item a{
  color:#374151; text-decoration:none; padding:3px 6px; border-radius:6px;
  transition: background .15s ease, color .15s ease, transform .15s ease;
}
.breadcrumb-item a:hover{ background:#eef2f7; color:#111827; transform:translateY(-1px); }
.breadcrumb-item .current{ font-weight:700; color:#111827; padding:3px 6px; }
@media (prefers-color-scheme: dark){
  .breadcrumb-list{
    background: rgba(28,30,33,.74); border-color: rgba(255,255,255,.08);
    box-shadow: 0 10px 24px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.06);
  }
  .breadcrumb-item{ color:#c5cad3; }
  .breadcrumb-item + .breadcrumb-item::before{ color:#9aa3b2; }
  .breadcrumb-item a{ color:#e4e8ef; }
  .breadcrumb-item a:hover{ background: rgba(255,255,255,.09); color:#fff; }
  .breadcrumb-item .current{ color:#fff; }
}
@media (max-width:640px){
  .breadcrumb-nav{ max-width: calc(100vw - 20px); right:10px; }
  .breadcrumb-list{ overflow:auto; -webkit-overflow-scrolling:touch; }
}
</style>
<?php } ?>

<?php
$posClass = $fixed ? 'breadcrumb-fixed' : 'breadcrumb-absolute';
$inlineStyle = sprintf('top:%dpx;right:%dpx;', $top, $right);
?>
<div class="breadcrumb-anchor">
  <nav class="breadcrumb-nav <?= $posClass ?>" style="<?= e($inlineStyle) ?>"
       aria-label="breadcrumb" itemscope itemtype="https://schema.org/BreadcrumbList">
    <ol class="breadcrumb-list">
      <?php $pos=1; foreach ($breadcrumb as $label => $href): ?>
        <li class="breadcrumb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
          <?php if ($href): ?>
            <a href="<?= e($href) ?>" itemprop="item"><span itemprop="name"><?= e($label) ?></span></a>
          <?php else: ?>
            <span class="current" aria-current="page" itemprop="name"><?= e($label) ?></span>
          <?php endif; ?>
          <meta itemprop="position" content="<?= $pos++ ?>">
        </li>
      <?php endforeach; ?>
    </ol>
  </nav>
</div>
<?php
echo $fixed
  ? '<style>.breadcrumb-fixed{position:fixed !important;}</style>'
  : '<style>.breadcrumb-absolute{position:absolute !important;}</style>';
