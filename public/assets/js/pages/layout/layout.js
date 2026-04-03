// 📄 /assets/js/pages/layout/layout.js

(function () {

    // 🔥 Bootstrap modal focus 문제 전체 해결
    document.addEventListener(
        'hide.bs.modal',
        function () {
            if (document.activeElement) {
                document.activeElement.blur();
            }
        },
        true
    );

    // 🔥 tooltip 잔상 제거
    document.addEventListener('hidden.bs.modal', function () {
        const tips = document.querySelectorAll('.tooltip');
        tips.forEach(t => t.remove());
    });

    const timerEl = document.getElementById('session-timer');
    if (!timerEl) return;

    let expireTime = (parseInt(timerEl.dataset.expireTime || '0', 10)) * 1000; // ms
    const sessionTimeout = (parseInt(timerEl.dataset.sessionTimeout || '0', 10)) * 60;
    const sessionAlert = (parseInt(timerEl.dataset.sessionAlert || '0', 10)) * 60;

    let sessionExpired = false;
    let popupShown = false;

    // 툴팁 캐시 및 상태
    let tooltipInst = null;
    let lastFullText = '';
    let pendingFullText = null;

    function createOrGetTooltip(el, initialContent) {
        try {
            if (typeof bootstrap === 'undefined' || !bootstrap || !bootstrap.Tooltip) {
                el.removeAttribute('data-bs-original-title');
                el.removeAttribute('title');
                el.setAttribute('data-bs-original-title', initialContent);
                lastFullText = initialContent;
                return null;
            }

            if (!tooltipInst) {
                el.removeAttribute('title');
                el.setAttribute('data-bs-original-title', initialContent);
                tooltipInst = new bootstrap.Tooltip(el, { container: 'body', placement: 'bottom', trigger: 'hover focus' });

                el.addEventListener('hidden.bs.tooltip', function () {
                    if (pendingFullText !== null) {
                        try {
                            tooltipInst.setContent && tooltipInst.setContent({ '.tooltip-inner': pendingFullText });
                        } catch (e) {
                            el.setAttribute('data-bs-original-title', pendingFullText);
                        }
                        lastFullText = pendingFullText;
                        pendingFullText = null;
                    }
                }, { passive: true });

                lastFullText = initialContent;
            } else {
                el.removeAttribute('title');
                el.setAttribute('data-bs-original-title', initialContent);
                lastFullText = initialContent;
            }

            return tooltipInst;
        } catch (e) {
            try { el.setAttribute('data-bs-original-title', initialContent); } catch (e2) {}
            lastFullText = initialContent;
            return null;
        }
    }

    function updateClock() {
        const el = document.getElementById('current-time');
        if (!el) return;
        if (el.dataset && el.dataset.format === 'full') return;

        const now = new Date();
        const hour = now.getHours();
        const hh12 = String(hour % 12 === 0 ? 12 : hour % 12).padStart(2, '0');
        const mm = String(now.getMinutes()).padStart(2, '0');
        const ampm = hour < 12 ? '오전' : '오후';
        const shortText = `${ampm} ${hh12}:${mm}`;

        const days = ['일','월','화','수','목','금','토'];
        const yyyy = now.getFullYear();
        const m = now.getMonth() + 1;
        const d = now.getDate();
        const day = days[now.getDay()];
        const ss = String(now.getSeconds()).padStart(2, '0');
        const fullText = `${yyyy}년${m}월${d}일(${day}) ${ampm} ${hh12}:${mm}:${ss}`;

        if (el.textContent !== shortText) el.textContent = shortText;
        el.style.cursor = 'pointer';

        if (typeof bootstrap !== 'undefined' && bootstrap && bootstrap.Tooltip) {
            const inst = createOrGetTooltip(el, fullText);
            let tipEl = null;
            try { tipEl = inst && inst.getTipElement ? inst.getTipElement() : null; } catch (e) { tipEl = null; }
            const isShown = tipEl ? tipEl.classList.contains('show') : false;

            if (isShown) {
                if (fullText !== lastFullText) pendingFullText = fullText;
            } else {
                if (fullText !== lastFullText) {
                    try {
                        if (inst && inst.setContent) {
                            inst.setContent({ '.tooltip-inner': fullText });
                        } else {
                            el.setAttribute('data-bs-original-title', fullText);
                        }
                    } catch (e) {
                        el.setAttribute('data-bs-original-title', fullText);
                    }
                    lastFullText = fullText;
                }
            }
        } else {
            if (el.matches(':hover')) {
                if (fullText !== lastFullText) pendingFullText = fullText;
            } else {
                if (fullText !== lastFullText) {
                    el.setAttribute('data-bs-original-title', fullText);
                    el.removeAttribute('title');
                    lastFullText = fullText;
                    pendingFullText = null;
                }
            }
            if (!el._hasLeaveHandler) {
                el.addEventListener('mouseleave', function () {
                    if (pendingFullText !== null) {
                        el.setAttribute('data-bs-original-title', pendingFullText);
                        lastFullText = pendingFullText;
                        pendingFullText = null;
                    }
                }, { passive: true });
                el._hasLeaveHandler = true;
            }
        }        
    }

    function updateSessionTimer() {
        const now = Date.now();
        const remain = Math.floor((expireTime - now) / 1000);
        let sessionText = '';
        
    
        if (remain > 0) {
            const min = Math.floor(remain / 60);
            const sec = String(remain % 60).padStart(2, '0');
            sessionText = `남은시간 ${min}:${sec}`;
    
            // 팝업 표시 시점
            if (sessionAlert > 0 && remain <= sessionAlert && !popupShown) {
                popupShown = true;
                const audio = document.getElementById('session-alert-sound');
                if (audio) { try { audio.play(); } catch (e) {} }
    
                const popupWidth = 400;
                const popupHeight = 250;
                const left = window.screenX + (window.outerWidth - popupWidth) / 2;
                const top = window.screenY + (window.outerHeight - popupHeight) / 2;
                const features = [
                    `width=${popupWidth}`,
                    `height=${popupHeight}`,
                    `left=${left}`,
                    `top=${top}`,
                    'toolbar=no','menubar=no','scrollbars=no','resizable=no','location=no','status=no'
                ].join(',');
    
                const popup = window.open('/autologout/extend', 'SessionExtend', features);
                window.SessionExtend = popup;
    
                if (!popup || popup.closed || typeof popup.closed === 'undefined') {
                    alert('팝업이 차단되어 있습니다.\n브라우저의 팝업 차단을 해제하고 다시 시도하세요.');
                } else {
                    window.addEventListener('message', function (e) {
                        if (e.data && e.data.type === 'sessionExtended') {
                            popupShown = false;
                            expireTime = e.data.expireTime * 1000;
                        }
                    }, { once: true });
                }
            }
        } else {
    
            // --------- 여기부터가 가장 중요한 수정 영역 ---------
            sessionText = '세션 만료';
    
            if (!sessionExpired) {
                sessionExpired = true;
    
                // 팝업 강제 닫기 (오류 무시)
                if (window.SessionExtend) {
                    try { window.SessionExtend.close(); } catch (e) {}
                }
    
                alert('세션이 만료되었습니다. 다시 로그인 해주세요.');
    
                // redirect를 딜레이 시켜 브라우저 정책 회피 + 안정적인 세션 종료
                setTimeout(() => {
                    window.location.replace('/logout?type=expired');
                }, 200);
            }
            // --------- 수정 끝 ---------
        }
    
        timerEl.innerText = sessionText;
    }
    

    function extendSession() {
        fetch('/autologout/keepalive', {
            method: 'GET',
            credentials: 'include'
        })
            .then(response => {
                if (!response.ok) throw new Error('HTTP 상태: ' + response.status);
                return response.json();
            })
            .then(data => {
                if (data && data.success) {
                    expireTime = data.expire_time * 1000;
                    popupShown = false;
                } else {
                    alert('세션이 만료되었습니다. 다시 로그인 해주세요.');
                    window.location.href = '/auth/logout';
                }
            })
            .catch(error => {
                console.error('연장 fetch 에러:', error);
                alert('서버와의 연결에 문제가 있습니다.\n' + error.message);
            });
    }

    function logoutWithPopupClose() {
        if (window.SessionExtend && !window.SessionExtend.closed) {
            window.SessionExtend.postMessage({ type: 'logout' }, '*');
        }
        window.location.href = '/auth/logout';
    }

    window.extendSession = extendSession;
    window.logoutWithPopupClose = logoutWithPopupClose;

    // ----------------- 미니 달력 구현 -----------------
    const miniCalEl = document.getElementById('mini-calendar');
    const timeEl = document.getElementById('current-time');
    let calState = { year: null, month: null }; // month: 0-11
    let isCalOpen = false;

    (function injectCalStyles(){
        const css = `
#mini-calendar { position: absolute; z-index: 1200; background:#fff; color:#222; border-radius:8px; box-shadow: 0 6px 18px rgba(0,0,0,0.25); padding:8px; min-width:260px; font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; }
#mini-calendar .cal-header { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:4px 6px; }
#mini-calendar .cal-nav { cursor:pointer; border:none; background:transparent; color:#333; font-weight:600; padding:4px 8px; border-radius:4px; }
#mini-calendar .cal-nav:focus { outline:2px solid rgba(13,110,253,0.25); }
#mini-calendar .cal-title { font-weight:700; font-size:14px; }
#mini-calendar .cal-grid { display:grid; grid-template-columns:repeat(7,34px); gap:6px; justify-content:center; padding:6px 4px; }
#mini-calendar .cal-day { width:34px; height:34px; display:flex; align-items:center; justify-content:center; border-radius:6px; cursor:pointer; user-select:none; }
#mini-calendar .cal-day:hover { background:#e9eefc; }
#mini-calendar .cal-day.out { color:#aaa; cursor:default; background:transparent; }
#mini-calendar .cal-day.today { background:#0d6efd; color: #fff; font-weight:700; }
#mini-calendar .cal-weekday { font-size:11px; color:#666; text-align:center; }
#mini-calendar .cal-weekday.sun, #mini-calendar .cal-day.sun { color: #d9534f; } /* 일요일 빨강 */
#mini-calendar .cal-weekday.sat, #mini-calendar .cal-day.sat { color: #0d6efd; } /* 토요일 파랑 */
`;
        const s = document.createElement('style');        
        s.appendChild(document.createTextNode(css));
        document.head.appendChild(s);
    })();

    function positionCalendar() {
        if (!miniCalEl || !timeEl) return;
        const rect = timeEl.getBoundingClientRect();
        const calRect = miniCalEl.getBoundingClientRect();
        // 기본: 아래로 표시, 화면 밖이면 위로 표시
        let top = window.scrollY + rect.bottom + 6;
        let left = window.scrollX + rect.left;
        if (left + calRect.width > window.scrollX + window.innerWidth) {
            left = window.scrollX + window.innerWidth - calRect.width - 8;
        }
        if (top + calRect.height > window.scrollY + window.innerHeight) {
            top = window.scrollY + rect.top - calRect.height - 6;
        }
        miniCalEl.style.top = top + 'px';
        miniCalEl.style.left = left + 'px';
    }

    function buildCalendar(year, month) {
        calState.year = year; calState.month = month;
        const first = new Date(year, month, 1);
        const startDay = first.getDay(); // 0-6
        const daysInMonth = new Date(year, month+1, 0).getDate();

        const prevDays = startDay; // number of leading cells
        const totalCells = Math.ceil((prevDays + daysInMonth) / 7) * 7;

        const today = new Date();
        const isSameMonth = today.getFullYear() === year && today.getMonth() === month;

        let html = '<div class="cal-header">';
        html += `<button type="button" class="cal-nav" data-action="prev" aria-label="이전 달">&lt;</button>`;
        html += `<div class="cal-title">${year}년 ${month+1}월</div>`;
        html += `<button type="button" class="cal-nav" data-action="next" aria-label="다음 달">&gt;</button>`;
        html += '</div>';

        // weekdays
        const wdays = ['일','월','화','수','목','금','토'];
        html += '<div class="cal-grid">';
        for (let w=0; w<7; w++) {
            const cls = ['cal-weekday'];
            if (w === 0) cls.push('sun');
            if (w === 6) cls.push('sat');
            html += `<div class="${cls.join(' ')}">${wdays[w]}</div>`;
        }
        // days
        for (let i=0; i<totalCells; i++) {
            const dayNum = i - prevDays + 1;
            if (dayNum < 1 || dayNum > daysInMonth) {
                html += `<div class="cal-day out"></div>`;
            } else {
                const weekday = (startDay + dayNum - 1) % 7;
                const classes = ['cal-day'];
                if (isSameMonth && dayNum === today.getDate()) classes.push('today');
                if (weekday === 0) classes.push('sun');
                if (weekday === 6) classes.push('sat');
                html += `<div class="${classes.join(' ')}" data-day="${dayNum}" data-weekday="${weekday}">${dayNum}</div>`;
            }
        }
        html += '</div>';
        miniCalEl.innerHTML = html;

        // 바인딩: nav 버튼 클릭 시 전파 차단하여 외부 클릭 핸들러와 충돌 방지
        miniCalEl.querySelectorAll('[data-action="prev"]').forEach(b => b.addEventListener('click', function(e){ e.stopPropagation(); onPrevMonth(e); }));
        miniCalEl.querySelectorAll('[data-action="next"]').forEach(b => b.addEventListener('click', function(e){ e.stopPropagation(); onNextMonth(e); }));
        miniCalEl.querySelectorAll('.cal-day[data-day]').forEach(d => d.addEventListener('click', onSelectDay));
    }

    function onPrevMonth(e) {
        if (e && e.stopPropagation) e.stopPropagation();
        let y = calState.year, m = calState.month - 1;
        if (m < 0) { m = 11; y -= 1; }
        buildCalendar(y, m);
        positionCalendar();
    }
    function onNextMonth(e) {
        if (e && e.stopPropagation) e.stopPropagation();
        let y = calState.year, m = calState.month + 1;
        if (m > 11) { m = 0; y += 1; }
        buildCalendar(y, m);
        positionCalendar();
    }
    function onSelectDay(e) {
        const day = parseInt(e.currentTarget.getAttribute('data-day'), 10);
        if (!day) return;
        const selected = new Date(calState.year, calState.month, day);
        const iso = selected.toISOString().slice(0,10); // YYYY-MM-DD

        if (timeEl) timeEl.dataset.selectedDate = iso;

        // 툴팁/라벨 갱신
        const days = ['일','월','화','수','목','금','토'];
        const label = `${selected.getFullYear()}년${selected.getMonth()+1}월${selected.getDate()}일(${days[selected.getDay()]})`;
        try {
            if (typeof tooltipInst !== 'undefined' && tooltipInst && tooltipInst.setContent) {                
                tooltipInst.setContent({ '.tooltip-inner': label });
                timeEl.setAttribute('data-bs-original-title', label);
            } else {
                timeEl.setAttribute('data-bs-original-title', label);
            }
        } catch (err) {
            timeEl.setAttribute('data-bs-original-title', label);
        }

        document.dispatchEvent(new CustomEvent('miniCalendarSelect', { detail: { date: iso, dateObj: selected } }));

        const action = timeEl.dataset.action || '';
        const urlTemplate = timeEl.dataset.url || '';
        if (action === 'navigate' && urlTemplate) {
            const targetUrl = urlTemplate.replace('{date}', encodeURIComponent(iso));
            window.location.href = targetUrl;
            return;
        }

        closeCalendar();
    }

    function openCalendar() {
        if (!miniCalEl || !timeEl) return;
        if (isCalOpen) return;
        const now = new Date();
        const sel = timeEl.dataset.selectedDate ? new Date(timeEl.dataset.selectedDate) : now;
        buildCalendar(sel.getFullYear(), sel.getMonth());
        miniCalEl.classList.remove('d-none');
        miniCalEl.setAttribute('aria-hidden', 'false');
        positionCalendar();
        isCalOpen = true;

        setTimeout(() => {
            document.addEventListener('click', onDocClick);
            window.addEventListener('resize', positionCalendar);
            document.addEventListener('keydown', onDocKeydown);
        }, 0);
    }

    function closeCalendar() {
        if (!miniCalEl) return;
        miniCalEl.classList.add('d-none');
        miniCalEl.setAttribute('aria-hidden', 'true');
        isCalOpen = false;
        document.removeEventListener('click', onDocClick);
        window.removeEventListener('resize', positionCalendar);
        document.removeEventListener('keydown', onDocKeydown);
    }

    function onDocClick(e) {
        if (!miniCalEl || !timeEl) return;
        if (miniCalEl.contains(e.target) || timeEl.contains(e.target)) return;
        closeCalendar();
    }

    function onDocKeydown(e) {
        if (e.key === 'Escape') closeCalendar();
    }

    if (timeEl && miniCalEl) {
        timeEl.addEventListener('click', function (e) {
            e.stopPropagation();
            try {
                if (typeof bootstrap !== 'undefined' && bootstrap && bootstrap.Tooltip) {
                    const inst = bootstrap.Tooltip.getInstance(timeEl) || tooltipInst;
                    if (inst && typeof inst.hide === 'function') inst.hide();
                    timeEl.removeAttribute('title');
                } else {
                    timeEl.removeAttribute('title');
                }
            } catch (err) {
                console && console.debug && console.debug('hide tooltip error', err);
            }

            if (isCalOpen) closeCalendar(); else openCalendar();
        });
        timeEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
                e.preventDefault();
                try {
                    if (typeof bootstrap !== 'undefined' && bootstrap && bootstrap.Tooltip) {
                        const inst = bootstrap.Tooltip.getInstance(timeEl) || tooltipInst;
                        if (inst && typeof inst.hide === 'function') inst.hide();
                        timeEl.removeAttribute('title');
                    } else {
                        timeEl.removeAttribute('title');
                    }
                } catch (err) {
                    console && console.debug && console.debug('hide tooltip error', err);
                }

                if (isCalOpen) closeCalendar(); else openCalendar();
            }
        });
    }
    // ----------------- 미니 달력 구현 끝 -----------------

// 초기 실행
setInterval(updateClock, 1000);
updateClock();

// 약간의 짧은 딜레이만 주기 (1500ms → 300~500ms)
setTimeout(() => {
    setInterval(updateSessionTimer, 1000);
    updateSessionTimer();
}, 300);



})();