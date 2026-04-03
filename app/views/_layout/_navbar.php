<?php
// 경로: PROJECT_ROOT . '/app/views/_layout/_navbar.php'
?>
<nav class="navbar navbar-expand-sm navbar-light bg-white border-bottom box-shadow">
    <div class="container-fluid">

        <!-- 로고 -->
        <div id="Logo" class="logo">
            <div class="logobox">
                <span class="rectangle" style="--t: 1px; width: 15px; height: 15px"></span>
                <span class="circle" style="--t: 1.9px; width: 15px; height: 15px"></span>
                <span class="triangle" style="--t: 1px; width: 15px; height: 15px"></span>
            </div>
            <a href="/" style="text-decoration: none; color: inherit;" translate="no">
                <h1 class="animated">
                    <span>S</span><span>U</span><span>K</span><span>H</span><span>Y</span><span>A</span><span>N</span><span>G</span>
                </h1>
            </a>
        </div>

        <!-- 토글 버튼 -->
        <button class="navbar-toggler" type="button"
            data-bs-toggle="collapse" data-bs-target="#mainNavbar"
            aria-controls="mainNavbar" aria-expanded="false"
            aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- 메뉴 항목 -->
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link text-dark" href="/about">우리회사는지금</a></li>
                <li class="nav-item"><a class="nav-link text-dark" href="/vision">앞으로의비전</a></li>
                <li class="nav-item"><a class="nav-link text-dark" href="/contact">문의하기</a></li>
            </ul>

            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link text-dark" href="/login">로그인</a></li>
                <li class="nav-item"><a class="nav-link text-dark" href="/register">회원가입</a></li>
                <li class="nav-item"><a class="nav-link text-dark" href="/privacy">Privacy</a></li>
            </ul>
        </div>

    </div>
</nav>