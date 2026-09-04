SET NAMES utf8mb4;

CREATE TABLE institution_daily_employment_income_groups (
    id VARCHAR(36) NOT NULL,
    daily_employment_income_id VARCHAR(36) NOT NULL,
    sort_no INT NOT NULL DEFAULT 0,
    business_unit VARCHAR(30) NOT NULL,
    project_id VARCHAR(36) NULL,
    work_team_id VARCHAR(36) NULL,
    work_description VARCHAR(500) NOT NULL,
    default_daily_rate DECIMAL(18,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by VARCHAR(100) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_daily_income_group_header_sort (daily_employment_income_id, sort_no, id),
    KEY idx_daily_income_group_dimensions (business_unit, project_id, work_team_id, daily_employment_income_id),
    KEY idx_daily_income_group_project (project_id),
    KEY idx_daily_income_group_team (work_team_id),
    CONSTRAINT fk_daily_income_group_header FOREIGN KEY (daily_employment_income_id)
        REFERENCES institution_daily_employment_incomes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_daily_income_group_project FOREIGN KEY (project_id)
        REFERENCES system_projects(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_daily_income_group_team FOREIGN KEY (work_team_id)
        REFERENCES system_work_teams(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT ck_daily_income_group_description CHECK (CHAR_LENGTH(TRIM(work_description)) > 0),
    CONSTRAINT ck_daily_income_group_rate CHECK (default_daily_rate >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='일용근로소득 근무그룹 SSOT';

ALTER TABLE institution_daily_employment_income_items
    ADD COLUMN daily_employment_income_group_id VARCHAR(36) NULL AFTER id,
    ADD KEY idx_daily_income_item_group (daily_employment_income_group_id, sort_no, id);

INSERT INTO institution_daily_employment_income_groups (
    id,daily_employment_income_id,sort_no,business_unit,project_id,work_team_id,
    work_description,default_daily_rate,created_at,created_by,updated_at,updated_by
)
SELECT
    UUID(),i.daily_employment_income_id,MIN(i.sort_no),i.business_unit,i.project_id,i.work_team_id,
    '기존 자료 이관',
    CASE WHEN COUNT(DISTINCT w.daily_rate_amount)=1 THEN MIN(w.daily_rate_amount) ELSE 0 END,
    MIN(i.created_at),MIN(i.created_by),MAX(i.updated_at),MAX(i.updated_by)
FROM institution_daily_employment_income_items i
LEFT JOIN institution_daily_employment_income_workdays w ON w.daily_employment_income_item_id=i.id
GROUP BY i.daily_employment_income_id,i.business_unit,i.project_id,i.work_team_id;

UPDATE institution_daily_employment_income_items i
JOIN institution_daily_employment_income_groups g
  ON g.daily_employment_income_id=i.daily_employment_income_id
 AND g.business_unit=i.business_unit
 AND g.project_id <=> i.project_id
 AND g.work_team_id <=> i.work_team_id
SET i.daily_employment_income_group_id=g.id;

DELIMITER $$
CREATE PROCEDURE verify_daily_income_group_backfill()
BEGIN
    IF EXISTS (SELECT 1 FROM institution_daily_employment_income_items WHERE daily_employment_income_group_id IS NULL LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Daily income group backfill left unlinked items';
    END IF;
    IF EXISTS (
        SELECT daily_employment_income_group_id,worker_client_id
        FROM institution_daily_employment_income_items
        GROUP BY daily_employment_income_group_id,worker_client_id HAVING COUNT(*)>1 LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Daily income group backfill found duplicate workers';
    END IF;
END$$
DELIMITER ;
CALL verify_daily_income_group_backfill();
DROP PROCEDURE verify_daily_income_group_backfill;

ALTER TABLE institution_daily_employment_income_items
    DROP CONSTRAINT fk_daily_item_header,
    DROP CONSTRAINT fk_daily_item_project,
    DROP CONSTRAINT fk_daily_item_team,
    DROP CONSTRAINT ck_daily_item_scope;

ALTER TABLE institution_daily_employment_income_items
    DROP INDEX uq_daily_income_item_business,
    DROP INDEX idx_daily_income_item_worker,
    DROP INDEX idx_daily_income_item_business_unit,
    DROP INDEX idx_daily_income_item_header,
    DROP COLUMN daily_employment_income_id,
    DROP COLUMN business_unit,
    DROP COLUMN work_scope_code,
    DROP COLUMN scope_project_key,
    DROP COLUMN project_id,
    DROP COLUMN work_team_id,
    DROP COLUMN work_team_scope_key,
    MODIFY COLUMN daily_employment_income_group_id VARCHAR(36) NOT NULL,
    ADD UNIQUE KEY uq_daily_income_group_worker (daily_employment_income_group_id, worker_client_id),
    ADD KEY idx_daily_income_item_worker (worker_client_id, daily_employment_income_group_id);

ALTER TABLE institution_daily_employment_income_items
    ADD CONSTRAINT fk_daily_item_group FOREIGN KEY (daily_employment_income_group_id)
        REFERENCES institution_daily_employment_income_groups(id) ON DELETE RESTRICT ON UPDATE CASCADE;
