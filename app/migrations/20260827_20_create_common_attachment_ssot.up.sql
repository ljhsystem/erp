DELIMITER $$
CREATE PROCEDURE migrate_20260827_20_up()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (
            'system_attachments','institution_daily_income_non_tax_revision_attachments'
        )
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='공용 Attachment 테이블 일부 또는 전체가 이미 존재합니다.';
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE()
          AND TABLE_NAME='institution_daily_employment_income_non_taxable_revisions'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='비과세 Revision Migration이 먼저 필요합니다.';
    END IF;

    CREATE TABLE system_attachments (
        id VARCHAR(36) NOT NULL,
        original_file_name VARCHAR(255) NOT NULL,
        mime_type VARCHAR(150) NOT NULL,
        file_size BIGINT UNSIGNED NOT NULL,
        sha256_hash CHAR(64) NOT NULL,
        storage_object_key VARCHAR(500) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_by VARCHAR(100) NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by VARCHAR(100) NOT NULL,
        deleted_at DATETIME NULL,
        deleted_by VARCHAR(100) NULL,
        restored_at DATETIME NULL,
        restored_by VARCHAR(100) NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_system_attachment_storage_key (storage_object_key),
        KEY idx_system_attachment_hash (sha256_hash,file_size),
        KEY idx_system_attachment_deleted (deleted_at),
        CONSTRAINT ck_system_attachment_size CHECK (file_size > 0),
        CONSTRAINT ck_system_attachment_hash CHECK (sha256_hash REGEXP '^[0-9a-f]{64}$'),
        CONSTRAINT ck_system_attachment_delete_actor CHECK (
            (deleted_at IS NULL AND deleted_by IS NULL) OR (deleted_at IS NOT NULL AND deleted_by IS NOT NULL)
        )
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
      COMMENT='공용 비공개 Attachment 원본 메타데이터';

    CREATE TABLE institution_daily_income_non_tax_revision_attachments (
        id VARCHAR(36) NOT NULL,
        non_taxable_revision_id VARCHAR(36) NOT NULL,
        attachment_id VARCHAR(36) NOT NULL,
        sort_no INT UNSIGNED NOT NULL DEFAULT 0,
        link_status_code VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
        linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        linked_by VARCHAR(100) NOT NULL,
        released_at DATETIME NULL,
        released_by VARCHAR(100) NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_daily_non_tax_revision_attachment (non_taxable_revision_id,attachment_id),
        KEY idx_daily_non_tax_attachment (attachment_id,link_status_code),
        CONSTRAINT fk_daily_non_tax_attachment_revision FOREIGN KEY (non_taxable_revision_id)
            REFERENCES institution_daily_employment_income_non_taxable_revisions(id)
            ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_daily_non_tax_attachment_file FOREIGN KEY (attachment_id)
            REFERENCES system_attachments(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT ck_daily_non_tax_attachment_status CHECK
            (link_status_code IN ('DRAFT','LOCKED','RELEASED')),
        CONSTRAINT ck_daily_non_tax_attachment_release CHECK (
            (link_status_code IN ('DRAFT','LOCKED') AND released_at IS NULL AND released_by IS NULL)
            OR (link_status_code='RELEASED' AND released_at IS NOT NULL AND released_by IS NOT NULL)
        )
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
      COMMENT='일용근로소득 비과세 Revision Attachment 연결';
END$$
CALL migrate_20260827_20_up()$$
DROP PROCEDURE migrate_20260827_20_up$$
DELIMITER ;

INSERT INTO system_file_upload_policies
    (id,policy_key,policy_name,bucket,allowed_ext,allowed_mime,max_size_mb,is_active,description,created_at,created_by,updated_at,updated_by)
SELECT '20260827-2000-4000-8000-000000000001','daily_income_non_taxable_evidence',
       '일용근로소득 비과세 근거자료','private://attachment','pdf,jpg,jpeg,png',
       'application/pdf,image/jpeg,image/png',20,1,
       '일용근로소득 비과세 Revision 확인용 비공개 근거자료',
       NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
WHERE NOT EXISTS (
    SELECT 1 FROM system_file_upload_policies WHERE policy_key='daily_income_non_taxable_evidence'
);
