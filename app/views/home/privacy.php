<?php
// 경로: PROJECT_ROOT . '/app/views/home/privacy.php'
use Core\Helpers\AssetHelper;
// 1. 프로젝트 루트 상수 정의
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__, 3));
}

// 2. 공용 asset() 함수 로드 (대소문자 주의)
//require_once PROJECT_ROOT . '/core/Helpers/AssetHelper.php';

// 3. 페이지 제목 설정
$pageTitle = "Privacy Policy";

// 4. 본문 버퍼링 시작
ob_start();
?>

<main class="flex-grow-1 intro-page">

    <!-- 타이틀 -->
    <div class="container intro-title">
        <h1 translate="no"><?= $pageTitle ?></h1>
        <p translate="no">개인정보처리방침</p>
    </div>

    <div class="container privacy-policy py-5">

        <p>(주)석향은 이용자의 개인정보를 소중하게 여기며, 개인정보 보호법 등 관련 법령을 준수하고 있습니다. 본 방침은 회사가 수집하는 개인정보의 항목, 처리 목적, 보유기간 및 이용자의 권리에 대해 설명합니다.</p>

        <h2>1. 수집하는 개인정보 항목</h2>
        <ul>
            <li>필수항목: 성명, 이메일 주소, 휴대전화 번호, 아이디, 비밀번호</li>
            <li>선택항목: 회사명, 부서, 직책, 생년월일, 지역</li>
            <li>자동 수집항목: 서비스 이용기록, 접속 로그, 쿠키, 접속 IP, 브라우저 종류, 기기정보 등</li>
        </ul>

        <h2>2. 개인정보 수집 및 이용 목적</h2>
        <ul>
            <li>회원가입 및 사용자 인증, 서비스 이용에 따른 본인 식별</li>
            <li>서비스 제공에 필요한 계약 이행 및 고객 상담/불만 처리</li>
            <li>이벤트 정보 안내 및 마케팅 활용 (사전 동의 시)</li>
            <li>법령 및 이용약관 위반행위 확인 및 제한 조치</li>
        </ul>

        <h2>3. 개인정보 보유 및 이용 기간</h2>
        <p>회사는 개인정보를 수집 목적 달성 후 지체 없이 파기합니다. 단, 법령에 따라 다음과 같이 보관될 수 있습니다.</p>
        <ul>
            <li>계약 또는 청약철회 기록: 5년</li>
            <li>대금 결제 및 재화 공급 기록: 5년</li>
            <li>소비자 불만 또는 분쟁 처리 기록: 3년</li>
        </ul>

        <h2>4. 개인정보의 제3자 제공</h2>
        <p>회사는 원칙적으로 개인정보를 외부에 제공하지 않습니다. 단, 법령 근거 또는 동의가 있을 경우 예외적으로 제공될 수 있습니다.</p>

        <h2>5. 개인정보 처리 위탁</h2>
        <ul>
            <li>호스팅 및 서버 운영: Azure Korea</li>
            <li>SMS 발송 서비스: LG U+ 메시징 시스템</li>
            <li>이메일 발송: SendGrid</li>
        </ul>

        <h2>6. 이용자의 권리 및 행사 방법</h2>
        <p>이용자는 언제든지 자신의 개인정보를 조회/정정/삭제하거나 동의를 철회할 수 있습니다. 회사는 본인 확인 후 지체 없이 조치합니다.</p>

        <h2>7. 개인정보의 파기 절차 및 방법</h2>
        <ul>
            <li>전자파일: 복구 불가 방식으로 영구 삭제</li>
            <li>종이 문서: 분쇄 또는 소각</li>
        </ul>

        <h2>8. 개인정보 보호책임자 안내</h2>

        <div class="contact p-3 bg-light border-start border-secondary">
            <strong>책임자:</strong> jh.Lee<br>
            <strong>소속:</strong> (주)석향 개인정보보호팀<br>
            <strong>연락처:</strong> suk-hyang@daum.net / 070-4821-0400<br>
            <strong>주소:</strong> 경기도 광주시 탄벌길 58, 나동 102호<br>
            개인정보 관련 문의는 언제든지 이메일로 접수해 주세요.
        </div>

    </div>
</main>

<?php
// 5. 버퍼링 종료 → 레이아웃 본문 변수에 저장
$content = ob_get_clean();

// 6. 페이지 전용 CSS/JS 로드 (정답: /assets)
$pageStyles = AssetHelper::css('/assets/css/pages/home/privacy.css');
$pageScripts = ''; // 별도 JS 없음




// 7. 공용 레이아웃 연결
include PROJECT_ROOT . '/app/views/_layout/_layout.php';
