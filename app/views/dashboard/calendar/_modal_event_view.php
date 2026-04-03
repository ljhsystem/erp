<!-- 📄 /app/views/dashboard/calendar/_modal_event_view.php -->
<div id="modal-view" class="shint-modal is-hidden" role="dialog" aria-modal="true" aria-labelledby="view-title">
  <div class="shint-modal__card shint-modal__card--view">
    <header class="shint-modal__head">
      <div class="shint-viewtitle-wrap">
        <span class="shint-viewtitle" id="view-title"></span>
        <span class="shint-view-dot" id="view-event-dot"></span>
      </div>
      <button type="button" class="shint-iconbtn" data-close="modal" aria-label="닫기">×</button>
    </header>

    <div class="shint-modal__body">
      <div class="shint-viewmeta">
        <div class="shint-vrow">
          <div class="shint-ico">🏷️</div>
          <div class="shint-vtext">
            <div class="shint-vsub" id="view-calendar-name">캘린더</div>
          </div>
        </div>

        <div class="shint-vrow">
          <div class="shint-ico">👤</div>
          <div class="shint-vtext">
            <div class="shint-vsub" id="view-owner">담당자</div>
          </div>
        </div>

        <div class="shint-vrow">
          <div class="shint-ico">🕒</div>

          <div class="shint-vtext">
            <div class="shint-vsub" id="view-period"></div>

            <!-- ✅ 날짜 바로 아래 -->
            <div id="view-repeat" class="view-repeat is-hidden"></div>
          </div>
        </div>


        <div class="shint-vrow">
          <div class="shint-ico">📍</div>
          <div class="shint-vtext">
            <div class="shint-vsub" id="view-location"></div>
          </div>
        </div>

        <div class="shint-vrow">
          <div class="shint-ico">📝</div>
          <div class="shint-vtext">
            <div class="shint-vdesc" id="view-description"></div>
          </div>
        </div>

        <!-- 게스트 -->
        <div class="shint-vrow" id="view-guests-row">
          <div class="shint-ico">👥</div>
          <div class="shint-vtext" id="view-guests"></div>
        </div>

        <!-- 첨부파일 -->
        <div class="shint-vrow">
          <div class="shint-ico">📎</div>
          <div class="shint-vtext">
            <div class="shint-vsub" id="view-attachments">없음</div>
          </div>
        </div>

        <!-- 알림 -->
        <div class="shint-vrow" id="view-alarms-row">
          <div class="shint-ico">🔔</div>
          <div class="shint-vtext" id="view-alarms"></div>
        </div>


      </div>
    </div>

    <footer class="shint-viewactions" id="view-actions">
      <button type="button" class="shint-iconbtn2" id="btn-edit" title="수정" aria-label="수정">✏️</button>
      <button type="button" class="shint-iconbtn2" id="btn-delete" title="삭제" aria-label="삭제">🗑️</button>

      <!-- <button  type="button"  id="btn-hard-delete"  class="shint-btn shint-btn--danger">  완전 삭제</button> -->
      
      <div class="shint-morewrap">
        <button type="button" class="shint-iconbtn2" id="btn-more" title="더보기" aria-label="더보기">⋯</button>

        <div id="view-more-menu" class="shint-moremenu is-hidden" role="menu" aria-label="더보기 메뉴">
          <button type="button" class="shint-moreitem" data-action="copy-text">
            텍스트 복사
          </button>
          <button type="button" class="shint-moreitem" data-action="copy-image">
            이미지 복사
          </button>
        </div>
      </div>
    </footer>
  </div>
</div>
