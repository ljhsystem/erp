const workday = (day, rate = 200000, taxable = 0) => ({
    work_date: `2025-08-${String(day).padStart(2, '0')}`,
    actual_work_minutes: 480,
    daily_rate_amount: rate,
    taxable_additional_amount: taxable,
    non_taxable_additional_amount: 0,
});

const worker = (key, id, name, workType, description, days, collapsed = true) => ({
    client_key: key,
    worker_client_id: id,
    worker_name_fixture: name,
    work_type_code: workType,
    work_description: description,
    collapsed,
    workdays: days,
    calculation: null,
});

export const DAILY_EMPLOYMENT_INCOME_VISUAL_FIXTURE = Object.freeze({
    fixture_only: true,
    header: {
        income_year_month: '2025-08',
        document_title: '2025년 08월 일용근로소득 지급 내역',
    },
    groups: [
        { client_key: 'fixture-group-1', business_unit: 'CONSTRUCTION', project_id: 'fixture-project-cheongju', work_team_id: 'fixture-team-stone-1', work_description: '외벽 석재 붙임공사', collapsed: false, items: [
            worker('fixture-worker-1', 'fixture-client-1', '최충성', 'STONE', '석재 재단 및 붙임', [3, 4, 5, 6, 11, 12, 13, 14].map((day, index) => workday(day, 200000, index === 0 ? 10000 : 0)), false),
            worker('fixture-worker-2', 'fixture-client-2', '최봉일', 'JOINT', '줄눈·메지 마감', [4, 5, 6, 11, 12, 13, 14].map(day => workday(day, 180000))),
            worker('fixture-worker-3', 'fixture-client-3', '김영식', 'TRANSPORT', '석재·부자재 운반', [5, 6, 12, 13, 14, 18].map(day => workday(day, 170000))),
        ] },
        { client_key: 'fixture-group-2', business_unit: 'CONSTRUCTION', project_id: 'fixture-project-2', work_team_id: 'fixture-team-2', work_description: '현장작업', collapsed: true, items: [worker('fixture-worker-4', 'fixture-client-4', '작업자4', 'STONE', '현장 석공 작업', [1, 2, 3].map(day => workday(day)))] },
        { client_key: 'fixture-group-3', business_unit: 'HEAD_OFFICE', project_id: null, work_team_id: null, work_description: '사무실 보수', collapsed: true, items: [worker('fixture-worker-5', 'fixture-client-5', '작업자5', 'REPAIR', '사무실 화장실 보수', [7, 8].map(day => workday(day, 150000))), worker('fixture-worker-6', 'fixture-client-6', '작업자6', 'REPAIR', '내부 정리', [8].map(day => workday(day, 150000)))] },
        { client_key: 'fixture-group-4', business_unit: 'ECOMMERCE', project_id: null, work_team_id: null, work_description: '배송 오류 회수·처리', collapsed: true, items: [worker('fixture-worker-7', 'fixture-client-7', '작업자7', 'DELIVERY', '배송 회수', [20, 21].map(day => workday(day, 140000)))] },
    ],
});
