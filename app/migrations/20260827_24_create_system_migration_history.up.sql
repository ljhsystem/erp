CREATE TABLE system_migration_history (
    id VARCHAR(36) NOT NULL,
    migration_id VARCHAR(191) NOT NULL,
    plan_id VARCHAR(191) NOT NULL,
    checksum CHAR(64) NOT NULL,
    status_code VARCHAR(20) NOT NULL,
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    applied_by VARCHAR(100) NOT NULL COMMENT 'ActorHelper Actor Token',
    database_engine VARCHAR(50) NOT NULL,
    database_version VARCHAR(100) NOT NULL,
    error_code VARCHAR(100) NULL,
    superseded_by VARCHAR(191) NULL,
    schema_fingerprint CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_system_migration_history (migration_id,plan_id),
    KEY idx_system_migration_plan (plan_id,status_code,started_at),
    KEY idx_system_migration_superseded (superseded_by),
    CONSTRAINT ck_system_migration_status CHECK
        (status_code IN ('STARTED','APPLIED','FAILED','SUPERSEDED')),
    CONSTRAINT ck_system_migration_checksum CHECK (checksum REGEXP '^[0-9a-f]{64}$'),
    CONSTRAINT ck_system_migration_fingerprint CHECK
        (schema_fingerprint IS NULL OR schema_fingerprint REGEXP '^[0-9a-f]{64}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='공식 Migration Plan 실행·대체 이력';
