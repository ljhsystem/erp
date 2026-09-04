SET NAMES utf8mb4;

DELIMITER $$
CREATE PROCEDURE rollback_daily_income_groups()
BEGIN
    IF EXISTS (
        SELECT g.daily_employment_income_id,g.business_unit,g.project_id,g.work_team_id,i.worker_client_id
        FROM institution_daily_employment_income_groups g
        JOIN institution_daily_employment_income_items i ON i.daily_employment_income_group_id=g.id
        GROUP BY g.daily_employment_income_id,g.business_unit,g.project_id,g.work_team_id,i.worker_client_id
        HAVING COUNT(DISTINCT g.id)>1 LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Multiple groups cannot be represented by the previous daily income grain';
    END IF;

    ALTER TABLE institution_daily_employment_income_items
        ADD COLUMN daily_employment_income_id VARCHAR(36) NULL AFTER id,
        ADD COLUMN business_unit VARCHAR(30) NULL AFTER sort_no,
        ADD COLUMN work_scope_code VARCHAR(20) NULL AFTER business_unit,
        ADD COLUMN project_id VARCHAR(36) NULL AFTER work_scope_code,
        ADD COLUMN work_team_id VARCHAR(36) NULL AFTER project_id;

    UPDATE institution_daily_employment_income_items i
    JOIN institution_daily_employment_income_groups g ON g.id=i.daily_employment_income_group_id
    SET i.daily_employment_income_id=g.daily_employment_income_id,
        i.business_unit=g.business_unit,
        i.work_scope_code=CASE WHEN g.project_id IS NULL THEN 'HEAD_OFFICE' ELSE 'PROJECT' END,
        i.project_id=g.project_id,
        i.work_team_id=g.work_team_id;

    ALTER TABLE institution_daily_employment_income_lines
        DROP FOREIGN KEY fk_daily_line_item;
    ALTER TABLE institution_daily_employment_income_workdays
        DROP FOREIGN KEY fk_daily_workday_item;
    ALTER TABLE ledger_evidence_daily_employment_income
        DROP FOREIGN KEY fk_daily_evidence_item;
    ALTER TABLE institution_daily_employment_income_items
        DROP FOREIGN KEY fk_daily_item_worker;
    ALTER TABLE institution_daily_employment_income_items
        DROP FOREIGN KEY fk_daily_item_group;

    ALTER TABLE institution_daily_employment_income_items
        DROP INDEX uq_daily_income_group_worker,
        DROP INDEX idx_daily_income_item_worker,
        DROP INDEX idx_daily_income_item_group,
        MODIFY COLUMN daily_employment_income_id VARCHAR(36) NOT NULL,
        MODIFY COLUMN business_unit VARCHAR(30) NOT NULL,
        MODIFY COLUMN work_scope_code VARCHAR(20) NOT NULL,
        ADD COLUMN scope_project_key VARCHAR(50) GENERATED ALWAYS AS (IFNULL(project_id,'NO_PROJECT')) STORED AFTER project_id,
        ADD COLUMN work_team_scope_key VARCHAR(50) GENERATED ALWAYS AS (IFNULL(work_team_id,'NO_WORK_TEAM')) STORED AFTER work_team_id,
        DROP COLUMN daily_employment_income_group_id;

    ALTER TABLE institution_daily_employment_income_items
        ADD UNIQUE KEY uq_daily_income_item_business (daily_employment_income_id,business_unit,scope_project_key,work_team_scope_key,worker_client_id),
        ADD KEY idx_daily_income_item_worker (worker_client_id,daily_employment_income_id),
        ADD KEY idx_daily_income_item_business_unit (business_unit,work_team_id,worker_client_id),
        ADD KEY idx_daily_income_item_header (daily_employment_income_id),
        ADD CONSTRAINT ck_daily_item_scope CHECK ((work_scope_code='PROJECT' AND project_id IS NOT NULL) OR (work_scope_code='HEAD_OFFICE' AND project_id IS NULL));

    ALTER TABLE institution_daily_employment_income_items
        ADD CONSTRAINT fk_daily_item_header FOREIGN KEY (daily_employment_income_id) REFERENCES institution_daily_employment_incomes(id);
    ALTER TABLE institution_daily_employment_income_items
        ADD CONSTRAINT fk_daily_item_project FOREIGN KEY (project_id) REFERENCES system_projects(id);
    ALTER TABLE institution_daily_employment_income_items
        ADD CONSTRAINT fk_daily_item_team FOREIGN KEY (work_team_id) REFERENCES system_work_teams(id);
    ALTER TABLE institution_daily_employment_income_items
        ADD CONSTRAINT fk_daily_item_worker FOREIGN KEY (worker_client_id) REFERENCES system_clients(id);

    ALTER TABLE institution_daily_employment_income_workdays
        ADD CONSTRAINT fk_daily_workday_item FOREIGN KEY (daily_employment_income_item_id)
            REFERENCES institution_daily_employment_income_items(id);
    ALTER TABLE institution_daily_employment_income_lines
        ADD CONSTRAINT fk_daily_line_item FOREIGN KEY (daily_employment_income_item_id)
            REFERENCES institution_daily_employment_income_items(id);
    ALTER TABLE ledger_evidence_daily_employment_income
        ADD CONSTRAINT fk_daily_evidence_item FOREIGN KEY (daily_employment_income_item_id)
            REFERENCES institution_daily_employment_income_items(id);

    DROP TABLE institution_daily_employment_income_groups;
END$$
DELIMITER ;
CALL rollback_daily_income_groups();
DROP PROCEDURE rollback_daily_income_groups;
