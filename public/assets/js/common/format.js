// 경로: PROJECT_ROOT . '/assets/js/common/format.js'
export function onlyNumber(val){
    return String(val ?? '').replace(/\D/g,'');
}

/* 사업자번호 */
export function formatBizNumber(val){

    val = onlyNumber(val);

    if(val.length <= 3) return val;
    if(val.length <= 5) return val.replace(/(\d{3})(\d+)/,'$1-$2');

    return val.replace(/(\d{3})(\d{2})(\d+)/,'$1-$2-$3');
}

/* 법인번호 / 주민번호 */
export function formatCorpNumber(val){

    val = onlyNumber(val);

    if(val.length <= 6) return val;

    return val.replace(/(\d{6})(\d+)/,'$1-$2');
}

/* 휴대폰 */
export function formatMobile(val){

    val = onlyNumber(val);

    if(val.length <= 3) return val;
    if(val.length <= 7) return val.replace(/(\d{3})(\d+)/,'$1-$2');

    return val.replace(/(\d{3})(\d{4})(\d+)/,'$1-$2-$3');
}

/* 일반전화 / 팩스 */
export function formatPhone(val){

    val = onlyNumber(val);

    if(val.startsWith("02")){

        if(val.length <= 2) return val;
        if(val.length <= 5) return val.replace(/(\d{2})(\d+)/,'$1-$2');
        if(val.length <= 9) return val.replace(/(\d{2})(\d{3})(\d+)/,'$1-$2-$3');

        return val.replace(/(\d{2})(\d{4})(\d+)/,'$1-$2-$3');

    }else{

        if(val.length <= 3) return val;
        if(val.length <= 6) return val.replace(/(\d{3})(\d+)/,'$1-$2');
        if(val.length <= 10) return val.replace(/(\d{3})(\d{3})(\d+)/,'$1-$2-$3');

        return val.replace(/(\d{3})(\d{4})(\d+)/,'$1-$2-$3');

    }
}

/* 날짜: 0000-00-00 같은 값 숨김 */
export function formatDateDisplay(val){
    const v = String(val ?? '').trim();

    if(
        v === '' ||
        v === '0000-00-00' ||
        v === '0000-00-00 00:00:00' ||
        v === 'null' ||
        v === 'undefined'
    ){
        return '';
    }

    return v;
}

/* 금액 표시: 소수점 제거 + 천단위 콤마 */
export function formatAmount(val){
    const num = Number(
        String(val ?? '')
            .replace(/,/g, '')
            .trim()
    );

    if(!Number.isFinite(num)) return '';

    return Math.trunc(num).toLocaleString('ko-KR');
}

/* 금액 저장용: 콤마 제거 + 숫자/소수점만 허용 */
export function unformatAmount(val){
    const cleaned = String(val ?? '')
        .replace(/,/g, '')
        .replace(/[^\d.]/g, '')
        .trim();

    if(cleaned === '') return '';

    const num = Number(cleaned);

    if(!Number.isFinite(num)) return '';

    return String(Math.trunc(num));
}