UPDATE `system_menu_registry` SET `page_key`=NULL, `updated_at`=CURRENT_TIMESTAMP
WHERE `page_key` IN (
    'approval.inbox','approval.personal_expense',
    'web.institution.human_resources.attendance','web.institution.human_resources.compensation_incentives',
    'web.institution.human_resources.employment_contracts','web.institution.human_resources.job_assignments',
    'web.institution.human_resources.performance_evaluations','web.institution.human_resources.personnel_actions',
    'web.institution.human_resources.qualification_education','web.institution.income_data.daily_employment',
    'web.institution.income_data.regular_employment','web.institution.national_tax','web.institution.local_tax',
    'web.institution.tax_agent','web.institution.filing_history','ledger.evidence_metadata'
);

DELETE FROM `system_page_registry` WHERE `page_key` IN (
    'approval.inbox','approval.personal_expense',
    'web.institution.human_resources.attendance','web.institution.human_resources.compensation_incentives',
    'web.institution.human_resources.employment_contracts','web.institution.human_resources.job_assignments',
    'web.institution.human_resources.performance_evaluations','web.institution.human_resources.personnel_actions',
    'web.institution.human_resources.qualification_education','web.institution.income_data.daily_employment',
    'web.institution.income_data.regular_employment','web.institution.national_tax','web.institution.local_tax',
    'web.institution.tax_agent','web.institution.filing_history','ledger.evidence_metadata'
);
