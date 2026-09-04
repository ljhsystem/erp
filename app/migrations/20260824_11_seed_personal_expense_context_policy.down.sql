-- 운영 정책·회계역할·감사정보는 자동 역변환하지 않는다.
DELETE FROM `ledger_account_context_ref_policies`
 WHERE `id` IN ('10c31fd2-3dbc-4c45-80fa-202608240011','abbe6ee3-f1b2-487f-b476-202608240012');
