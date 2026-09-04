INSERT INTO `system_page_registry` (
    `page_key`, `module_key`, `module_label`, `menu_key`, `menu_label`,
    `page_label`, `page_description`, `breadcrumb`, `default_route_key`,
    `default_route_url`, `source_description`, `is_active`
) VALUES
('approval.inbox','approval','전자결재','approval.main','결재','결재함','통합 전자결재 문서함','전자결재 > 결재 > 결재함','web.approval.inbox','/approval/status','Route 권한 PageRegistry 정규화',1),
('approval.personal_expense','approval','전자결재','approval.main','결재','개인경비 신청','개인경비 신청 및 결재','전자결재 > 결재 > 개인경비 신청','web.approval.personal-expense','/approval/personal-expense','Route 권한 PageRegistry 정규화',1),
('web.institution.human_resources.attendance','institution','대외기관업무','institution.human_resources','인사·노무관리','근태관리','실제 출퇴근·일별 근태·월 마감 관리','대외기관업무 > 인사·노무관리 > 근태관리','web.institution.human_resources.attendance','/institution/human-resources/attendance','Route 권한 PageRegistry 정규화',1),
('web.institution.human_resources.compensation_incentives','institution','대외기관업무','institution.human_resources','인사·노무관리','보상·인센티브관리','보상·인센티브 관리','대외기관업무 > 인사·노무관리 > 보상·인센티브관리','web.institution.human_resources.compensation_incentives','/institution/human-resources/compensation-incentives','Route 권한 PageRegistry 정규화',1),
('web.institution.human_resources.employment_contracts','institution','대외기관업무','institution.human_resources','인사·노무관리','근로계약관리','근로계약 작성 및 결재 관리','대외기관업무 > 인사·노무관리 > 근로계약관리','web.institution.human_resources.employment_contracts','/institution/human-resources/employment-contracts','Route 권한 PageRegistry 정규화',1),
('web.institution.human_resources.job_assignments','institution','대외기관업무','institution.human_resources','인사·노무관리','직무·배치관리','직원 직무·배치 현재상태 및 기간이력 관리','대외기관업무 > 인사·노무관리 > 직무·배치관리','web.institution.human_resources.job_assignments','/institution/human-resources/job-assignments','Route 권한 PageRegistry 정규화',1),
('web.institution.human_resources.performance_evaluations','institution','대외기관업무','institution.human_resources','인사·노무관리','성과평가관리','성과평가 관리','대외기관업무 > 인사·노무관리 > 성과평가관리','web.institution.human_resources.performance_evaluations','/institution/human-resources/performance-evaluations','Route 권한 PageRegistry 정규화',1),
('web.institution.human_resources.personnel_actions','institution','대외기관업무','institution.human_resources','인사·노무관리','인사발령관리','인사발령 기안·결재·적용 관리','대외기관업무 > 인사·노무관리 > 인사발령관리','web.institution.human_resources.personnel_actions','/institution/human-resources/personnel-actions','Route 권한 PageRegistry 정규화',1),
('web.institution.human_resources.qualification_education','institution','대외기관업무','institution.human_resources','인사·노무관리','자격·교육관리','직원 자격·교육·만료·갱신 관리','대외기관업무 > 인사·노무관리 > 자격·교육관리','web.institution.human_resources.qualification_education','/institution/human-resources/qualification-education','Route 권한 PageRegistry 정규화',1),
('web.institution.income_data.daily_employment','institution','대외기관업무','institution.income_data','소득자료관리','일용근로소득','일용근로소득 작성·계산·결재 관리','대외기관업무 > 소득자료관리 > 일용근로소득','web.institution.income_data.daily_employment','/institution/income-data/daily-employment','Route 권한 PageRegistry 정규화',1),
('web.institution.income_data.regular_employment','institution','대외기관업무','institution.income_data','소득자료관리','상용근로소득','상용근로소득 작성·계산·결재 관리','대외기관업무 > 소득자료관리 > 상용근로소득','web.institution.income_data.regular_employment','/institution/income-data/regular-employment','Route 권한 PageRegistry 정규화',1),
('web.institution.national_tax','institution','대외기관업무','institution.tax','세무업무','국세업무','국세 신고·납부 관리','대외기관업무 > 세무업무 > 국세업무','web.institution.national_tax','/institution/national-tax','Route 권한 PageRegistry 정규화',1),
('web.institution.local_tax','institution','대외기관업무','institution.tax','세무업무','지방세업무','지방세 신고·납부 관리','대외기관업무 > 세무업무 > 지방세업무','web.institution.local_tax','/institution/local-tax','Route 권한 PageRegistry 정규화',1),
('web.institution.tax_agent','institution','대외기관업무','institution.tax','세무업무','세무사업무','세무사 위임·신고 자료 관리','대외기관업무 > 세무업무 > 세무사업무','web.institution.tax_agent','/institution/tax-agent','Route 권한 PageRegistry 정규화',1),
('web.institution.filing_history','institution','대외기관업무','institution.filing','신고관리','신고이력','대외기관 신고 이력 관리','대외기관업무 > 신고관리 > 신고이력','web.institution.filing_history','/institution/filing-history','Route 권한 PageRegistry 정규화',1),
('ledger.evidence_metadata','ledger','회계관리','ledger.data','자료관리','증빙정책','증빙 자료유형별 정책 관리','회계관리 > 자료관리 > 증빙정책','web.ledger.evidence_metadata','/ledger/data/evidence-metadata','Route 권한 PageRegistry 정규화',1)
ON DUPLICATE KEY UPDATE
    `module_key`=VALUES(`module_key`), `module_label`=VALUES(`module_label`),
    `menu_key`=VALUES(`menu_key`), `menu_label`=VALUES(`menu_label`),
    `page_label`=VALUES(`page_label`), `page_description`=VALUES(`page_description`),
    `breadcrumb`=VALUES(`breadcrumb`), `default_route_key`=VALUES(`default_route_key`),
    `default_route_url`=VALUES(`default_route_url`), `source_description`=VALUES(`source_description`),
    `is_active`=1, `updated_at`=CURRENT_TIMESTAMP;

UPDATE `system_menu_registry` smr
INNER JOIN `system_page_registry` spr ON spr.`default_route_url`=smr.`default_entry`
SET smr.`page_key`=spr.`page_key`, smr.`updated_at`=CURRENT_TIMESTAMP
WHERE smr.`page_key` IS NULL AND spr.`page_key` IN (
    'approval.inbox','approval.personal_expense',
    'web.institution.human_resources.attendance','web.institution.human_resources.compensation_incentives',
    'web.institution.human_resources.employment_contracts','web.institution.human_resources.job_assignments',
    'web.institution.human_resources.performance_evaluations','web.institution.human_resources.personnel_actions',
    'web.institution.human_resources.qualification_education','web.institution.income_data.daily_employment',
    'web.institution.income_data.regular_employment','web.institution.national_tax','web.institution.local_tax',
    'web.institution.tax_agent','web.institution.filing_history','ledger.evidence_metadata'
);
