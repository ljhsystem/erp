<?php
// 경로: PROJECT_ROOT . '/app/views/layout/sidebar.php';
use Core\Helpers\AssetHelper;
?>
<?= AssetHelper::css('/assets/css/pages/layout/sidebar.css') ?>

<?php
$uri = trim($_SERVER['REQUEST_URI'], '/');
$section = explode('/', $uri)[0];
?>


<div class="sidebar <?= ($ui['sidebar_default'] === 'collapsed') ? 'collapsed' : '' ?>">
    <div class="section-title">
        <?php
        switch ($section) {
            case 'sukhyang':
                echo '📁 석향서류';
                break;
            case 'approval':
                echo '📝 결재서류';
                break;
            case 'ledger':
                echo '🧮 회계관리';
                break;
            case 'institution':
                echo '🏛 대외기관업무';
                break;
            case 'site':
                echo '🏗 현장관리';
                break;
            case 'notice':
                echo '📢 공지/회의';
                break;
            default:
                echo '🏠 메인';
                break;
        }
        ?>
    </div>


    <ul class="nav nav-pills flex-column mb-auto">

        <!-- 다른 섹션(대시보드, 석향서류, 결재서류)은 기존 구조 유지 -->
        <?php if ($section === 'dashboard'): ?>
            <li><a href="/dashboard" class="nav-link">🏠 대시보드</a></li>
            <li><a href="/dashboard/report" class="nav-link">📈 통합 보고서</a></li>
            <li><a href="/dashboard/activity" class="nav-link">🕒 최근 활동</a></li>
            <li><a href="/dashboard/notifications" class="nav-link">📢 공지사항</a></li>
            <li><a href="/dashboard/kpi" class="nav-link">📊 실적 현황</a></li>
            <li><a href="/dashboard/calendar" class="nav-link">📅 일정/캘린더</a></li>
            <li><a href="/dashboard/settings" class="nav-link">⚙️ 설정</a></li>
        <?php elseif ($section === 'sukhyang'): ?>
            <li><a href="/sukhyang" class="nav-link">📄 문서 대시보드</a></li>
            <li><a href="/sukhyang/file_register" class="nav-link">📝 문서 등록</a></li>
            <li><a href="/sukhyang/view" class="nav-link">🔍 문서 상세 보기</a></li>
            <li><a href="/sukhyang/edit" class="nav-link">✏️ 문서 수정</a></li>
            <li><a href="/sukhyang/stats" class="nav-link">📊 문서 통계</a></li>
        <?php elseif ($section === 'approval'): ?>
            <li><a href="/approval" class="nav-link">📑 결재 목록</a></li>
            <li><a href="/approval/write_expenditure" class="nav-link">💰 지출결의서 작성</a></li>
            <li><a href="/approval/write_purchase_request" class="nav-link">🛒 구매요청서</a></li>
            <li><a href="/approval/write_leave_request" class="nav-link">🌴 휴가신청서</a></li>
            <li><a href="/approval/write_trip_report" class="nav-link">🚗 출장보고서</a></li>
            <li><a href="/approval/write_work_report" class="nav-link">📝 업무보고서</a></li>
            <li><a href="/approval/write_review_request" class="nav-link">🔍 실행검토요청</a></li>
            <li><a href="/approval/write_progress_review" class="nav-link">📋 기성검토요청</a></li>
            <li><a href="/approval/write_foreign_remit" class="nav-link">💸 외화송금결재요청</a></li>
            <li><a href="/approval/write_free_draft" class="nav-link">📄 자유양식 기안서</a></li>
            <li><a href="/approval/status" class="nav-link">📊 결재 현황</a></li>
        <?php endif; ?>


        <?php if ($section === 'ledger'): ?>
            <!-- 거래원장 대시보드 -->
            <li><a href="/ledger" class="nav-link">📊 회계관리 대시보드</a></li>

            <!-- 기초정보 -->
            <li>
                <a href="#menu-ledger-basic" class="nav-link toggle" aria-expanded="false">
                    🧾 기초정보관리
                </a>
                <ul id="menu-ledger-basic" class="collapse">
                    <li><a href="/ledger/accounts" class="nav-link">📑 계정과목</a></li>
                    <li><a href="/ledger/description" class="nav-link">📝 적요</a></li>
                    <li><a href="/ledger/voucher-types" class="nav-link">📄 전표유형</a></li>
                    <li><a href="/ledger/payment-methods" class="nav-link">💳 결제수단</a></li>
                </ul>
            </li>


            <!-- 전표입력 -->
            <li>
                <a href="#menu-ledger-journal" class="nav-link toggle" aria-expanded="false">
                    📝 전표입력
                </a>
                <ul id="menu-ledger-journal" class="collapse">
                    <li><a href="/ledger/journal" class="nav-link">🧾 일반전표</a></li>
                    <li><a href="/ledger/input/tax_invoice" class="nav-link">📑 세금계산서</a></li>
                    <li><a href="/ledger/card/corporate" class="nav-link">💳 법인카드</a></li>
                    <li><a href="/ledger/card/personal" class="nav-link">👤 경비정산</a></li>
                    <li><a href="/ledger/bank" class="nav-link">🏦 입출금거래</a></li>
                    <li><a href="/ledger/input/withholding" class="nav-link">💸 원천징수</a></li>
                    <li><a href="/ledger/input/forex" class="nav-link">💱 외화전표</a></li>
                    <li><a href="/ledger/journal/review" class="nav-link">🔎 전표검토</a></li>
                </ul>
            </li>

            <!-- 장부관리 -->
            <li>
                <a href="#menu-ledger-book" class="nav-link toggle" aria-expanded="false">
                    📘 장부관리
                </a>
                <ul id="menu-ledger-book" class="collapse">
                    <li><a href="/ledger/book/journal" class="nav-link">📑 분개장</a></li>
                    <li><a href="/ledger/book/general" class="nav-link">📘 총계정원장</a></li>
                    <li><a href="/ledger/book/account" class="nav-link">📂 계정별원장</a></li>
                    <li><a href="/ledger/book/partner" class="nav-link">🧾 거래처원장</a></li>
                    <li><a href="/ledger/book/project_account" class="nav-link">💼 프로젝트계정별원장</a></li>
                    <li><a href="/ledger/book/daily_monthly" class="nav-link">📅 일/월계표</a></li>
                    <li><a href="/ledger/book/purchase_sale" class="nav-link">📄 매입매출장</a></li>
                    <li><a href="/ledger/book/car_log" class="nav-link">🚗 운행기록부</a></li>
                </ul>
            </li>

            <!-- 결산/마감 -->
            <li>
                <a href="#menu-ledger-closing" class="nav-link toggle" aria-expanded="false">
                    📅 결산/마감
                </a>
                <ul id="menu-ledger-closing" class="collapse">
                    <li><a href="/ledger/setup/opening_balance" class="nav-link">🔄 기초잔액입력</a></li>
                    <li><a href="/ledger/closing/monthly" class="nav-link">🔐 월마감처리</a></li>
                    <li><a href="/ledger/closing/carry_forward" class="nav-link">🧾 이월처리</a></li>
                </ul>
            </li>

            <!-- 결산/재무제표 -->
            <li>
                <a href="#menu-ledger-financial" class="nav-link toggle" aria-expanded="false">
                    📊 결산/재무제표
                </a>
                <ul id="menu-ledger-financial" class="collapse">
                    <li><a href="/ledger/closing/trial_balance" class="nav-link">📊 합계잔액시산표</a></li>
                    <li><a href="/ledger/closing/income_statement" class="nav-link">📉 손익계산서</a></li>
                    <li><a href="/ledger/closing/balance_sheet" class="nav-link">📈 재무상태표</a></li>
                    <li><a href="/ledger/closing/cost_statement" class="nav-link">🧮 원가명세서</a></li>
                    <li><a href="/ledger/closing/retained_earnings" class="nav-link">💼 이익잉여금처분</a></li>
                    <li><a href="/ledger/closing/equity_change" class="nav-link">📋 자본변동표</a></li>
                    <li><a href="/ledger/closing/cash_flow" class="nav-link">💵 현금흐름표</a></li>
                </ul>
            </li>

            <!-- 신고 -->
            <li>
                <a href="#menu-ledger-declaration" class="nav-link toggle" aria-expanded="false">
                    🏢 세무신고
                </a>
                <ul id="menu-ledger-declaration" class="collapse">
                    <li><a href="/ledger/report/regular_worker" class="nav-link">👨‍💼 상용근로자 신고</a></li>
                    <li><a href="/ledger/report/daily_worker" class="nav-link">👷 일용근로자 신고</a></li>
                    <li><a href="/ledger/report/business_income" class="nav-link">💼 사업소득 신고</a></li>
                    <li><a href="/ledger/report/vat" class="nav-link">🧾 부가세신고</a></li>
                    <li><a href="/ledger/report/corporate_tax" class="nav-link">🏢 법인세신고</a></li>
                </ul>
            </li>

            <!-- 관리 -->
            <li>
                <a href="#menu-ledger-manage" class="nav-link toggle" aria-expanded="false">
                    ⚙️ 자산관리
                </a>
                <ul id="menu-ledger-manage" class="collapse">
                    <li><a href="/ledger/manage/corporate" class="nav-link">🏢 법인등기관리</a></li>
                    <li><a href="/ledger/manage/license" class="nav-link">📄 면허등록/관리</a></li>
                    <li><a href="/ledger/manage/assets" class="nav-link">🏠 자산등록/관리</a></li>
                    <li><a href="/ledger/manage/depreciation" class="nav-link">📉 감가상각 자동분개</a></li>
                    <li><a href="/ledger/manage/payment" class="nav-link">💳 결재/결제현황</a></li>
                    <li><a href="/ledger/approval" class="nav-link">✅ 전표승인 프로세스</a></li>
                </ul>
            </li>

            <li>
                <a href="#menu-ledger-report" class="nav-link toggle" aria-expanded="false">
                    📋 경영분석
                </a>
                <ul id="menu-ledger-report" class="collapse">
                    <li><a href="/ledger/report/export" class="nav-link">📄 PDF/엑셀 출력</a></li>
                    <li><a href="/ledger/report/finance_kpi" class="nav-link">📊 재무비율 분석</a></li>
                    <li><a href="/ledger/report/costcenter_pl" class="nav-link">📋 부서/프로젝트별 손익</a></li>
                </ul>
            </li>

            <li>
                <a href="#menu-ledger-trade" class="nav-link toggle" aria-expanded="false">
                    🚢 무역관리
                </a>
                <ul id="menu-ledger-trade" class="collapse">
                    <li><a href="/trade/import_contract" class="nav-link">📄 수입계약</a></li>
                    <li><a href="/trade/remittance" class="nav-link">💱 수입송금</a></li>
                    <li><a href="/trade/customs" class="nav-link">🧾 통관관리</a></li>
                    <li><a href="/trade/logistics" class="nav-link">🚚 물류비관리</a></li>
                    <li><a href="/trade/import_cost" class="nav-link">📊 수입원가</a></li>
                    <li><a href="/trade/settlement" class="nav-link">🧮 수입정산</a></li>
                </ul>
            </li>

            <li>
                <a href="/ledger/search" class="nav-link">🔍 전표검색</a>
            </li>
        <?php endif; ?>


        <?php if ($section === 'institution'): ?>
            <li><a href="/institution" class="nav-link<?php echo ($action === 'tax_office') ? ' active' : ''; ?>">🧾 기관대시보드</a></li>
            <li><a href="/institution/tax_office" class="nav-link<?php echo ($action === 'tax_office') ? ' active' : ''; ?>">🧾 세무서(국세관련)</a></li>
            <li><a href="/institution/local_government" class="nav-link<?php echo ($action === 'local_government') ? ' active' : ''; ?>">🏛️ 지방자치단체(지방세관련)</a></li>
            <li><a href="/institution/welfare_corp" class="nav-link<?php echo ($action === 'welfare_corp') ? ' active' : ''; ?>">👷 근로복지공단(보수총액/고용산재신고)</a></li>
            <li><a href="/institution/health_insurance" class="nav-link<?php echo ($action === 'health_insurance') ? ' active' : ''; ?>">🏥 건강보험공단(건강보험신고)</a></li>
            <li><a href="/institution/pension" class="nav-link<?php echo ($action === 'pension') ? ' active' : ''; ?>">💳 국민연금관리공단(국민연금신고)</a></li>
            <li><a href="/institution/credit_guarantee" class="nav-link<?php echo ($action === 'credit_guarantee') ? ' active' : ''; ?>">🔒 신용보증기금(신용보증관리)</a></li>
            <li><a href="/institution/construction_assoc" class="nav-link<?php echo ($action === 'construction_assoc') ? ' active' : ''; ?>">🏗️ 대한전문건설협회(실적신고)</a></li>
            <li><a href="/institution/construction_union" class="nav-link<?php echo ($action === 'construction_union') ? ' active' : ''; ?>">🛡️ 전문건설공제조합(보증/공제관리)</a></li>
            <li><a href="/institution/engineer_assoc" class="nav-link<?php echo ($action === 'engineer_assoc') ? ' active' : ''; ?>">👨‍🔧 기술인협회(경력신고)</a></li>
            <li><a href="/institution/construction_worker_union" class="nav-link<?php echo ($action === 'construction_worker_union') ? ' active' : ''; ?>">👷‍♂️ 건설근로자공제회(퇴직공제부금신고)</a></li>
        <?php elseif ($section === 'site'): ?>
            <li><a href="/site" class="nav-link">📊 현장대시보드</a></li>
            <li><a href="/site/estimate" class="nav-link">📑 견적관리</a></li>
            <li><a href="/site/contract" class="nav-link">📄 계약관리</a></li>
            <li><a href="/site/execution" class="nav-link">📝 실행관리</a></li>
            <li><a href="/site/guarantee" class="nav-link">🔒 보증/보험관리</a></li>
            <li><a href="/site/progress" class="nav-link">📈 기성확정내역</a></li>
            <li><a href="/site/construction_progress" class="nav-link">🏢 시공기성확정내역</a></li>
            <li><a href="/site/transaction" class="nav-link">💳 거래내역</a></li>
            <li><a href="/site/safety" class="nav-link">🦺 안전관리</a></li>
        <?php elseif ($section === 'notice'): ?>
            <li><a href="/notice" class="nav-link">📋 공지대시보드</a></li>
            <li><a href="/notice/employee" class="nav-link">👤 직원별공지</a></li>
            <li><a href="/notice/department" class="nav-link">🏢 부서별공지</a></li>
            <li><a href="/notice/all" class="nav-link">🌐 전체공지</a></li>
        <?php endif; ?>
    </ul>
</div>
<div class="sidebar-right-border">
    <button id="sidebar-toggle-btn" class="sidebar-toggle-btn" aria-label="사이드바 접기">
        &#60;
    </button>
</div>