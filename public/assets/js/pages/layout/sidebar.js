// /assets/js/pages/layout/sidebar.js
(function () {

    'use strict';

    let layoutAnimFrame = null;
    let layoutAnimTimer = null;

    document.addEventListener('DOMContentLoaded', function () {

        const sidebar = document.querySelector('.sidebar');
        const toggleBtn = document.getElementById('sidebar-toggle-btn');

        if(!sidebar) return;

        /* ===============================
           SIDEBAR ACCORDION MENU
        =============================== */

        sidebar.addEventListener('click', function(e){

            if(!sidebar.contains(e.target)) return;

            const link = e.target.closest('.nav-link.toggle');
            if(!link) return;

            const targetId = link.getAttribute('href');
            if(!targetId || !targetId.startsWith('#')) return;

            const menu = sidebar.querySelector(targetId);
            if(!menu) return;

            e.preventDefault();

            const isOpen = menu.classList.contains('show');
            const opened = sidebar.querySelectorAll('.collapse.show');

            opened.forEach(function(el){

                if(el !== menu){

                    el.classList.remove('show');

                    const toggle = sidebar.querySelector('[href="#'+el.id+'"]');

                    if(toggle){
                        toggle.setAttribute('aria-expanded','false');
                    }
                }

            });

            if(isOpen){
                menu.classList.remove('show');
                link.setAttribute('aria-expanded','false');
            }else{
                menu.classList.add('show');
                link.setAttribute('aria-expanded','true');
            }

        });

        /* ===============================
           SIDEBAR COLLAPSE BUTTON
        =============================== */

        if(!toggleBtn) return;

        if(sidebar.classList.contains('collapsed')){
            toggleBtn.innerHTML = '<i class="bi bi-chevron-right"></i>';
            document.body.classList.add('is-sidebar-collapsed');
        }else{
            toggleBtn.innerHTML = '<i class="bi bi-chevron-left"></i>';
        }

        toggleBtn.addEventListener('click', function(){

            sidebar.classList.toggle('collapsed');

            const collapsed = sidebar.classList.contains('collapsed');

            document.body.classList.toggle('is-sidebar-collapsed', collapsed);

            localStorage.setItem('sidebar:collapsed', collapsed ? '1' : '0');

            toggleBtn.innerHTML = collapsed
                ? '<i class="bi bi-chevron-right"></i>'
                : '<i class="bi bi-chevron-left"></i>';

            startLayoutSync();

        });

    });

    function startLayoutSync(){

        stopLayoutSync();

        const startedAt = performance.now();
        const duration = 360; // CSS transition(.3s)보다 살짝 크게

        function tick(now){

            adjustAllDataTables();
            fireLayoutResize();

            if(now - startedAt < duration){
                layoutAnimFrame = requestAnimationFrame(tick);
            }else{
                stopLayoutSync();
                adjustAllDataTables();
                fireLayoutResize();
            }
        }

        layoutAnimFrame = requestAnimationFrame(tick);

        layoutAnimTimer = setTimeout(() => {
            stopLayoutSync();
            adjustAllDataTables();
            fireLayoutResize();
        }, duration + 50);
    }

    function stopLayoutSync(){

        if(layoutAnimFrame){
            cancelAnimationFrame(layoutAnimFrame);
            layoutAnimFrame = null;
        }

        if(layoutAnimTimer){
            clearTimeout(layoutAnimTimer);
            layoutAnimTimer = null;
        }
    }

    function adjustAllDataTables(){

        if(!window.jQuery) return;

        const $ = window.jQuery;

        if(!$.fn.DataTable) return;

        $.fn.dataTable.tables({ visible: true, api: true })
            .columns.adjust()
            .draw(false);
    }

    function fireLayoutResize(){
        window.dispatchEvent(new Event('resize'));
        document.dispatchEvent(new CustomEvent('sidebar:toggled'));
    }

})();