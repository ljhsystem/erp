<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/calendar.php'
use Core\Helpers\AssetHelper;
// 캐싱 방지
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
// 페이지 전용 CSS / JS (layout에서 사용)
$pageStyles  = $pageStyles  ?? '';
$pageScripts = $pageScripts ?? '';

$pageStyles = AssetHelper::css('https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css');
$pageStyles = AssetHelper::css('https://cdn.jsdelivr.net/npm/@fullcalendar/list@6.1.11/index.global.min.css');
$pageStyles = AssetHelper::css('/assets/css/pages/dashboard/calendar/calendar.css');
$pageScripts =
    AssetHelper::js('https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js') .
    AssetHelper::js('https://cdn.jsdelivr.net/npm/rrule@2.7.2/dist/es5/rrule.min.js') .
    AssetHelper::js('https://cdn.jsdelivr.net/npm/@fullcalendar/rrule@6.1.11/index.global.min.js') .

    AssetHelper::js('/assets/js/common/location.autocomplete.js') .

    "\n<!-- 1️⃣ 서버 / 상태 (전역) -->\n" .
    AssetHelper::js('/assets/js/pages/dashboard/calendar/api.js') .
    AssetHelper::js('/assets/js/pages/dashboard/calendar/store.js') .

    "\n<!-- 2️⃣ 상단 바 -->\n" .
    AssetHelper::js('/assets/js/pages/dashboard/calendar/topbar.js') .

    "\n<!-- 3️⃣ 좌측 사이드바 -->\n" .
    AssetHelper::js('/assets/js/pages/dashboard/calendar/sidebar.left.create.js') .
    AssetHelper::module('/assets/js/pages/dashboard/calendar/sidebar.left.mini.js') .
    AssetHelper::js('/assets/js/pages/dashboard/calendar/sidebar.left.list.js') .

    "\n<!-- 4️⃣ 메인 캘린더 -->\n" .
    AssetHelper::js('/assets/js/pages/dashboard/calendar/view.js') .

    "\n<!-- 5️⃣ 우측 사이드바 (🔥 AdminPicker 사용 → module) -->\n" .
    AssetHelper::module('/assets/js/pages/dashboard/calendar/sidebar.right.edit.js') .
    AssetHelper::module('/assets/js/pages/dashboard/calendar/sidebar.right.filter.js') .
    AssetHelper::module('/assets/js/pages/dashboard/calendar/sidebar.right.create.js') .
    AssetHelper::module('/assets/js/pages/dashboard/calendar/sidebar.right.list.js') .

    "\n<!-- 6️⃣ 모달 (AdminPicker 사용 → module) -->\n" .
    AssetHelper::module('/assets/js/pages/dashboard/calendar/modal.event.view.js') .
    AssetHelper::module('/assets/js/pages/dashboard/calendar/modal.event.edit.js') .
    AssetHelper::module('/assets/js/pages/dashboard/calendar/modal.event.edit.repeat.js') .
    AssetHelper::module('/assets/js/pages/dashboard/calendar/modal.task.view.js') .
    AssetHelper::module('/assets/js/pages/dashboard/calendar/modal.task.edit.js') .
    AssetHelper::module('/assets/js/pages/dashboard/calendar/modal.quick.js') .
    AssetHelper::module('/assets/js/pages/dashboard/calendar/modal.trash.js') .

    "\n<!-- 7️⃣ 🔗 최종 연결자 (항상 맨 마지막) -->\n" .
    AssetHelper::js('/assets/js/pages/dashboard/calendar/bootstrap.js');



// 브레드크럼프
// $breadcrumb = [
//     '홈' => '/dashboard',
//     '일정/캘린더' => '/dashboard/calendar'
// ];
?>

<?php //include_once __DIR__ . '/../layout/breadcrumb.php'; ?>




<div class="dashboard-content calendar-page">
  <!-- 🔥 공통 컨테이너 -->
  <div class="calendar-container">
      <!-- 상단바 -->
      <?php include __DIR__ . '/calendar/_topbar.php'; ?>

      <!-- 본문 -->
      <div class="calendar-shell right-collapsed">

        <!-- 좌측 -->
        <?php include __DIR__ . '/calendar/_sidebar_left.php'; ?>

        <!-- 메인 캘린더 -->
        <section class="calendar-center">

          <!-- 🔥 검색/뷰 상태 헤더 -->
          <div id="calendar-view-header" class="calendar-view-header"></div>

          <!-- 실제 캘린더 -->
          <div id="calendar"></div>

        </section>

        <aside class="calendar-right-wrap">
            <!-- 우측 리스트 -->
            <?php include __DIR__ . '/calendar/_sidebar_right_list.php'; ?>

            <!-- 우측 수정 -->
            <?php include __DIR__ . '/calendar/_sidebar_right_edit.php'; ?>
        </aside>
      
      </div>
    </div>
</div>

<!-- 모달 -->
<?php include __DIR__ . '/calendar/_modal_event_view.php'; ?>
<?php include __DIR__ . '/calendar/_modal_event_edit.php'; ?>
<?php include __DIR__ . '/calendar/_modal_event_edit_repeat.php'; ?>
<?php include __DIR__ . '/calendar/_modal_task_view.php'; ?>
<?php include __DIR__ . '/calendar/_modal_task_edit.php'; ?>
<?php include __DIR__ . '/calendar/_modal_quick.php'; ?>
<?php include __DIR__ . '/calendar/_modal_trash.php'; ?>

<!-- 피커 -->
<div class="picker-root">
  <div id="mini-picker" class="picker is-hidden"></div>
  <div id="base-picker" class="picker is-hidden"></div>
  <div id="datetime-picker" class="picker is-hidden"></div>
  <div id="today-picker" class="picker is-hidden"></div>
  <div id="time-list-picker" class="picker is-hidden"></div>
</div>