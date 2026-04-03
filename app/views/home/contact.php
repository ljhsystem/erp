<?php
// 경로: PROJECT_ROOT . '/app/views/home/contact.php';
use Core\Helpers\AssetHelper;
// 1. 프로젝트 루트 상수 정의
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__, 3));
}

// 2. 공용 asset() 함수 로드 (대소문자 주의)
//require_once PROJECT_ROOT . '/core/Helpers/AssetHelper.php';

// 3. 페이지 제목
$pageTitle = "Contact";

// 4. 본문 버퍼링 시작
ob_start();
?>

<main class="flex-grow-1 intro-page">

    <!-- 타이틀 -->
    <div class="container intro-title">
        <h1 translate="no"><?= $pageTitle ?></h1>
        <p translate="no">관리자에게 메세지를 보낼 수 있습니다.</p>
    </div>

    <section id="contact" class="contact">
        <div class="container">
            <div class="contact-me">

                <!-- 왼쪽 정보 -->
                <div class="left">
                    <div class="info-box">
                        <i class="fas fa-phone"></i>
                        <div class="info-text">
                            <strong>Phone</strong>
                            <span><a href="tel:07048210400">070-4821-0400</a></span>
                        </div>
                    </div>

                    <div class="info-box">
                        <i class="fas fa-envelope"></i>
                        <div class="info-text">
                            <strong>Email</strong>
                            <span><a href="mailto:suk-hyang@daum.net">suk-hyang@daum.net</a></span>
                        </div>
                    </div>

                    <div class="info-box">
                        <i class="fas fa-map-marker-alt"></i>
                        <div class="info-text">
                            <strong>Address</strong>
                            <span>58, Tanbeol-gil, Gwangju-si, Gyeonggi-do, Republic of Korea</span>
                        </div>
                    </div>

                    <div class="map-container">
                        <div id="daumRoughmapContainer1693204145723" class="root_daum_roughmap root_daum_roughmap_landing"></div>
                    </div>
                </div>

                <!-- 오른쪽 폼 -->
                <div class="right">
                    <div class="form-container">

                        <form method="post" action="/api/contact/send">
                            <div class="form-group">
                                <label for="FullName">이름</label>
                                <input type="text" class="form-control" id="FullName" name="FullName" placeholder="본인의 이름을 입력하세요." required>
                            </div>

                            <div class="form-group">
                                <label for="EmailId">이메일</label>
                                <input type="email" class="form-control" id="EmailId" name="EmailId" placeholder="본인의 이메일 주소를 입력하세요." required>
                            </div>

                            <div class="form-group">
                                <label for="MobileNo">휴대폰</label>
                                <input type="tel" class="form-control" id="MobileNo" name="MobileNo" placeholder="본인의 휴대폰 번호를 입력하세요." required>
                            </div>

                            <div class="form-group">
                                <label for="Subject">제목</label>
                                <input type="text" class="form-control" id="Subject" name="Subject" placeholder="제목을 입력해 주세요." required>
                            </div>

                            <div class="form-group">
                                <label for="Message">메시지</label>
                                <textarea class="form-control" id="Message" name="Message" rows="5" placeholder="관리자에게 요청할 메시지를 입력하세요." required></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary">Send</button>
                        </form>

                    </div>
                </div>

            </div>
        </div>
    </section>
</main>

<?php
// 5. 버퍼링 종료 → $content 저장
$content = ob_get_clean();

// 6. 페이지 전용 CSS/JS (정답: /assets/)
$pageStyles  = AssetHelper::css('/assets/css/pages/home/contact.css');

$pageScripts =
    '<script src="https://ssl.daumcdn.net/dmaps/map_js_init/roughmapLoader.js"></script>' .
    AssetHelper::js('/assets/js/pages/home/contact.js') .
    '
    <script>
    window.addEventListener("load", function(){
        new daum.roughmap.Lander({
            timestamp: "1693204145723",
            key: "2gz2p",
            mapWidth: "100%",
            mapHeight: "300px"
        }).render();
    });
    </script>
    '; 
// 7. 공용 레이아웃 적용
include PROJECT_ROOT . '/app/views/_layout/_layout.php';
