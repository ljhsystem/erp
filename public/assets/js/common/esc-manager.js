// 경로: /public/assets/js/common/esc-manager.js
(function(){

    "use strict";

    window.ESCStack = {

        handlers: [],

        push(fn){
            this.handlers.push(fn);
        },

        remove(fn){
            this.handlers = this.handlers.filter(h => h !== fn);
        },

        trigger(){

            if(this.handlers.length > 0){

                const top = this.handlers[this.handlers.length - 1];

                if(top){
                    top();
                    return true;
                }
            }

            return false;
        }
    };

    /* 전역 ESC (단 하나만 존재해야 함) */
    window.addEventListener('keydown', function(e){

        if(e.key !== 'Escape') return;
    
        /* 1️⃣ picker 먼저 */
        const handled = window.ESCStack.trigger();
    
        if(handled){
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            return;
        }
    
        /* 2️⃣ modal */
        const openModal = getTopVisibleModal();
    
        if(openModal){
            bootstrap.Modal.getOrCreateInstance(openModal, { focus: false })?.hide();
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
        }
    
    }, true);   // 🔥🔥🔥 캡처 단계

    function getTopVisibleModal(){
        const modals = Array.from(document.querySelectorAll('.modal.show'));

        if(modals.length === 0) return null;

        return modals
            .map((modal, index) => ({
                modal,
                index,
                zIndex: Number.parseInt(window.getComputedStyle(modal).zIndex, 10) || 0
            }))
            .sort((a, b) => {
                if(a.zIndex !== b.zIndex) return b.zIndex - a.zIndex;
                return b.index - a.index;
            })[0].modal;
    }

})();
