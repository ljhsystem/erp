import fs from 'node:fs';

const read = (path) => fs.readFileSync(path, 'utf8');
const view = read('app/views/dashboard/settings/organization/permission-assignment.php');
const entry = read('public/assets/js/pages/dashboard/settings/organization/permission-assignment/index.js');
const user = read('public/assets/js/pages/dashboard/settings/organization/permission-assignment/user-permission.js');
const role = read('public/assets/js/pages/dashboard/settings/organization/permission-assignment.js');
const routes = read('routes/api/system.php');
const metaService = read('app/Services/System/DataTableColumnMetaService.php');
const sticky = read('public/assets/js/pages/dashboard/settings/organization/permission-assignment/sticky-layout.js');
const permissionCss = read('public/assets/css/pages/dashboard/settings/permission-assignment.css');
const rolePermissionColumns = role.slice(role.indexOf('function buildPermissionColumns()'));

const permissionColumns = [
    'id', 'sort_no', 'page', 'permission_source', 'category', 'permission_key',
    'permission_name', 'description', 'page_key', 'is_active', 'created_at',
    'created_by', 'updated_at', 'updated_by',
];

const checks = {
    tabs_order: view.indexOf('individual-permission-tab') < view.indexOf('role-permission-tab'),
    mode_labels: ['역할별', '역할 + 개인', '개인별'].every((value) => view.includes(value)),
    removed_legacy_ui: !['변경 사유', '역할 따름', 'individualBulkEffect', 'individualPermissionReason']
        .some((value) => view.includes(value)),
    single_checkbox: user.includes('individual-permission-check') && !user.includes('individual-effect-check'),
    permission_physical_columns_in_db_order: permissionColumns.every((key, index, list) => (
        index === 0 || user.indexOf(`settingsKey:'${list[index - 1]}'`) < user.indexOf(`settingsKey:'${key}'`)
    )),
    role_permission_physical_columns_in_db_order: permissionColumns.every((key, index, list) => (
        index === 0
        || rolePermissionColumns.indexOf(`settingsKey: '${list[index - 1]}'`)
            < rolePermissionColumns.indexOf(`settingsKey: '${key}'`)
    )),
    permission_meta_auth_permissions_only: metaService.includes("return $this->columnsForTable('auth_permissions', $domain);")
        && !metaService.includes("foreach (['auth_permissions', 'auth_role_permissions']"),
    individual_user_composite_meta: user.includes("metaDomain:'individual-permission-users'")
        && metaService.includes("'individual-permission-users' => ['composite' => 'individual-permission-users']")
        && ['auth_users', 'auth_roles', 'user_employees', 'auth_user_permission_profiles', 'auth_user_permissions']
            .every((table) => metaService.includes(`'${table}' => [`)),
    permission_virtual_column_fixed: role.includes("title: '\\uAD8C\\uD55C'")
        && role.includes("settingsKey: 'role_permission_id',\n            __dtColumnKind: 'virtual'")
        && role.lastIndexOf("settingsKey: 'updated_by'") < role.lastIndexOf("settingsKey: 'role_permission_id'")
        && user.includes("title:'권한',__dtColumnKind:'virtual'")
        && user.includes("className:'text-center no-colvis'"),
    permission_schema_reset: user.includes('resetOnColumnSchemaChange:true')
        && role.includes('resetOnColumnSchemaChange: true'),
    individual_uses_role_tree: user.includes('d.permission_tree||[]') && user.includes('permissionSourcePriority'),
    common_toolbar_contract: user.includes('deleteButton:false,showCopyButton:true')
        && user.includes('deleteButton:false')
        && user.includes('pageLength:100')
        && user.includes('selectable:true'),
    individual_header_contract: ['individualPermissionMode', 'individualPermissionCheckAll', 'individualPermissionSave']
        .every((id) => view.includes(`id="${id}"`)),
    permission_count_contract: role.includes('설정: 페이지 ${configuredPageCount}개, 권한 ${configuredPermissionCount}개 / 전체: 페이지 ${totalPageCount}개, 권한 ${totalPermissionCount}개')
        && user.includes('설정: 페이지 ${configuredPageCount}개, 권한 ${configuredPermissionCount}개 / 전체: 페이지 ${pages.length}개, 권한 ${permissions.length}개'),
    role_header_always_visible_disabled: view.includes('id="permission-header" class="rp-actions"')
        && view.includes('id="permission-check-all" disabled')
        && view.includes('id="permission-save-btn" type="button" class="btn btn-sm btn-secondary" disabled'),
    individual_sticky_contract: user.includes('bindPermissionAssignmentStickyLayout();')
        && sticky.includes("'#individualUserListCard'")
        && sticky.includes("'#individualPermissionCard > .card-header'")
        && sticky.includes("'#individual-permission-table_wrapper .dt-top'")
        && permissionCss.includes('#individualUserListCard {')
        && permissionCss.includes('#individualPermissionCard > .card-header {')
        && permissionCss.includes('#individual-permission-table_wrapper {'),
    permission_toolbar_header_only_sticky: permissionCss.includes('권한목록은 카드 제목이 아니라 DataTable 버튼 라인과 테이블 헤더만 고정한다.')
        && permissionCss.includes('#permissionListCard > .card-header,\n.role-permission-page #individualPermissionCard > .card-header {')
        && permissionCss.includes('--dt-sticky-top: var(--rp-sticky-top);'),
    left_list_toolbar_sticky_contract: permissionCss.includes('좌측 목록은 카드 제목이 아니라 DataTable 버튼 라인부터 고정한다.')
        && permissionCss.includes('#roleListCard,\n.role-permission-page #individualUserListCard {')
        && permissionCss.includes('#role-list-table_wrapper .dt-top,\n.role-permission-page #individual-user-table_wrapper .dt-top {')
        && permissionCss.includes('top: var(--rp-sticky-top) !important;'),
    left_list_body_fixed_below_toolbar: permissionCss.includes('목록 카드 전체를 고정하되 카드 제목만 내비게이션 위로 밀어낸다.')
        && sticky.includes('elementTopOffset(roleListCard, roleToolbar, 70)')
        && sticky.includes('elementTopOffset(individualUserCard, individualUserToolbar, 70)')
        && permissionCss.includes('top: calc(var(--rp-sticky-top) - var(--rp-role-list-toolbar-offset));')
        && permissionCss.includes('top: calc(var(--rp-sticky-top) - var(--rp-individual-user-toolbar-offset));')
        && permissionCss.includes('overflow-y: auto !important;'),
    no_inline_notice_card: !view.includes('individualReadonlyReason') && !user.includes('individualReadonlyReason'),
    user_list_search_and_settings_only: user.includes('selectable:false,showCopyButton:false,deleteButton:false,showColumnVisibility:false'),
    user_list_without_footer: user.includes('searching:true,paging:false,info:false')
        && permissionCss.includes('#individual-user-table_wrapper .dt-bottom,')
        && permissionCss.includes('#individual-user-table_wrapper .dataTables_paginate {'),
    separate_table_settings: user.includes("pageKey:'settings.organization.user_permissions'")
        && user.includes("tableKey:'individual-permission-matrix'")
        && routes.includes('api.settings.rolepermission'),
    shared_meta_independent_storage: user.includes("metaDomain:'permission-assignment'")
        && role.includes("metaDomain: 'permission-assignment'")
        && user.includes('individual-permissions.v7')
        && role.includes('permission-matrix.flat.v4'),
    final_payload: ['permission_mode', 'permission_ids', 'state_version'].every((value) => user.includes(value)),
    no_legacy_payload: !['reason,', 'overrides', 'INHERIT', 'ALLOW', 'DENY'].some((value) => user.includes(value)),
    canonical_routes: ['list', 'detail', 'save']
        .every((action) => routes.includes(`/api/settings/organization/user-permission/${action}`)),
    canonical_entry: entry.includes("./user-permission.js?v=20260814-11")
        && entry.includes("../permission-assignment.js?v=20260814-9")
        && entry.includes('initUserPermission()'),
};

console.log(JSON.stringify(checks, null, 2));
if (Object.values(checks).some((value) => !value)) {
    process.exit(1);
}
