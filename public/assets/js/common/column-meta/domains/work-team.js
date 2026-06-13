import { defineDomainRegistry } from '../helpers.js';

export const WorkTeamColumnRegistry = defineDomainRegistry('work-team', [
    { key: 'sort_no', label: '\uc21c\ubc88', tableVisible: true, tableDefault: true, excelDownload: true, searchable: false, width: 80, type: 'number' },
    { key: 'team_name', label: '\ud300\uba85', required: true, tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, searchDefault: true, width: 180, type: 'text' },
    { key: 'team_leader_client_name', label: '\ud300\uc7a5', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 180, type: 'text' },
    { key: 'team_leader_client_id', label: '\ud300\uc7a5 \uac70\ub798\ucc98 ID', tableVisible: false, tableDefault: false, searchable: false, width: 220, type: 'text' },
    { key: 'note', label: '\ube44\uace0', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 220, type: 'textarea' },
    { key: 'memo', label: '\uba54\ubaa8', tableVisible: false, tableDefault: false, excelTemplate: true, excelDownload: true, searchable: true, width: 220, type: 'textarea' },
    { key: 'is_active', label: '\uc0c1\ud0dc', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, sortable: true, width: 90, type: 'boolean' },
    { key: 'created_at', label: '\ub4f1\ub85d\uc77c\uc2dc', tableVisible: false, tableDefault: false, searchable: true, searchDate: true, width: 160, type: 'datetime' },
    { key: 'created_by_name', label: '\ub4f1\ub85d\uc790', tableVisible: false, tableDefault: false, searchable: false, width: 120, type: 'text' },
    { key: 'updated_at', label: '\uc218\uc815\uc77c\uc2dc', tableVisible: false, tableDefault: false, searchable: true, searchDate: true, width: 160, type: 'datetime' },
    { key: 'updated_by_name', label: '\uc218\uc815\uc790', tableVisible: false, tableDefault: false, searchable: false, width: 120, type: 'text' },
    { key: 'deleted_at', label: '\uc0ad\uc81c\uc77c\uc2dc', tableVisible: false, tableDefault: false, searchable: false, width: 160, type: 'datetime' },
    { key: 'deleted_by_name', label: '\uc0ad\uc81c\uc790', tableVisible: false, tableDefault: false, searchable: false, width: 120, type: 'text' },
]);
