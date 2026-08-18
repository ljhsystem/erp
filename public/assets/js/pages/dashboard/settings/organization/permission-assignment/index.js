import { initUserPermission } from './user-permission.js?v=20260814-11';

initUserPermission();

let roleModuleLoaded = false;
document.getElementById('role-permission-tab')?.addEventListener('shown.bs.tab', async () => {
    if (!roleModuleLoaded) {
        roleModuleLoaded = true;
        await import('../permission-assignment.js?v=20260814-9');
    }
});
