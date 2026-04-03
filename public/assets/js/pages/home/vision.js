//📂 경로: /assets/js/pages/home/vision.js


// 텍스트 작성 및 삭제 애니메이션
document.addEventListener('DOMContentLoaded', () => {
    const spanEl = document.querySelector('#vision h2 span');
    if (!spanEl) return;

    const txtArr = [
        '일류입니다.',
        '실행 전문가입니다.',
        '큰 계획을 가지고 있습니다.',
    ];
    let index = 0;
    let currentTxt = '';

    const typingSpeed = 80;  // 한 글자 추가 속도(ms)
    const deletingSpeed = 60; // 한 글자 삭제 속도(ms)
    const holdTime = 2000;    // 전체 문장 유지 시간(ms)

    function writeTxt() {
        currentTxt = txtArr[index];
        let i = 0;
        spanEl.textContent = '';

        const addLetter = () => {
            if (i < currentTxt.length) {
                spanEl.textContent += currentTxt[i++];
                setTimeout(addLetter, typingSpeed);
            } else {
                setTimeout(deleteTxt, holdTime);
            }
        };
        addLetter();
    }

    function deleteTxt() {
        let i = currentTxt.length;
        const removeLetter = () => {
            if (i >= 0) {
                spanEl.textContent = currentTxt.substring(0, i--);
                setTimeout(removeLetter, deletingSpeed);
            } else {
                index = (index + 1) % txtArr.length;
                writeTxt();
            }
        };
        removeLetter();
    }

    writeTxt();
});


