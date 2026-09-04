function appAjax() {
    const client = window.AppAjax || window.AppCore?.AppAjax;
    if (!client) throw new Error('공용 통신 모듈을 불러오지 못했습니다. 페이지를 새로고침해 주세요.');
    return client;
}

export const loadPermissionMaster = api => appAjax().postForm(api, { mode: 'master' });
export const loadPermissionSelection = (api, roleId) => appAjax().postForm(api, { role_id: roleId, mode: 'selection' });
export const postPermissionJson = (api, payload) => appAjax().postJson(api, payload);
export const loadIndividualUsers = api => appAjax().postForm(api, {});
export const loadIndividualDetail = (api, userId) => appAjax().postJson(api, { user_id: userId });
export const saveIndividualPermissions = (api, payload) => appAjax().postJson(api, payload);
