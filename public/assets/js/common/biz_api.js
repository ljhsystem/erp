// 경로: PROJECT_ROOT . '/assets/js/common/biz_api.js'
export async function checkBusinessStatus(bizNo){

    const res = await fetch(
        '/api/integration/biz-status',
        {
            method:'POST',
            headers:{
                'Content-Type':'application/json'
            },
            body:JSON.stringify({
                business_number:bizNo
            })
        }
    );

    return await res.json();

}