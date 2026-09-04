INSERT INTO system_codes (id,sort_no,code_group,group_name,code,code_name,note,is_active,created_by,updated_by)
SELECT UUID(),COALESCE((SELECT MAX(sort_no) FROM system_codes),0)+seed.sort_order,seed.code_group,seed.group_name,seed.code,seed.code_name,'사업소득 소득자 세무 적격성 SSOT',1,'SYSTEM:MIGRATION','SYSTEM:MIGRATION'
FROM (
    SELECT 1 sort_order,'TAXPAYER_ENTITY_TYPE' code_group,'납세자 유형' group_name,'INDIVIDUAL' code,'개인' code_name UNION ALL
    SELECT 2,'TAXPAYER_ENTITY_TYPE','납세자 유형','CORPORATION','법인' UNION ALL
    SELECT 3,'RESIDENCY_STATUS','거주자 구분','RESIDENT','거주자' UNION ALL
    SELECT 4,'RESIDENCY_STATUS','거주자 구분','NON_RESIDENT','비거주자' UNION ALL
    SELECT 5,'INCOME_RECIPIENT_TYPE','소득자 유형','BUSINESS_INCOME','사업소득' UNION ALL
    SELECT 6,'INCOME_RECIPIENT_TYPE','소득자 유형','OTHER_INCOME','기타소득' UNION ALL
    SELECT 7,'INCOME_RECIPIENT_TYPE','소득자 유형','REGULAR_EMPLOYMENT','상용근로소득' UNION ALL
    SELECT 8,'INCOME_RECIPIENT_TYPE','소득자 유형','DAILY_EMPLOYMENT','일용근로소득' UNION ALL
    SELECT 9,'WITHHOLDING_POLICY','원천징수 정책','BUSINESS_INCOME_WITHHOLDING','사업소득 원천징수' UNION ALL
    SELECT 10,'CLIENT_TAX_PROFILE_VERIFICATION','세무 프로필 검증상태','UNVERIFIED','미검증' UNION ALL
    SELECT 11,'CLIENT_TAX_PROFILE_VERIFICATION','세무 프로필 검증상태','VERIFIED','검증 완료' UNION ALL
    SELECT 12,'CLIENT_TAX_PROFILE_VERIFICATION','세무 프로필 검증상태','REJECTED','검증 반려'
) seed
WHERE NOT EXISTS(SELECT 1 FROM system_codes current_code WHERE current_code.code_group=seed.code_group AND current_code.code=seed.code);

CREATE TABLE system_client_tax_profiles (
    id VARCHAR(36) NOT NULL,
    client_id VARCHAR(36) NOT NULL,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    taxpayer_entity_type VARCHAR(50) NOT NULL,
    residency_status VARCHAR(50) NOT NULL,
    income_recipient_type VARCHAR(50) NOT NULL,
    withholding_policy_code VARCHAR(50) NOT NULL,
    verification_status VARCHAR(30) NOT NULL,
    verified_at DATETIME NULL,
    verified_by VARCHAR(100) NULL COMMENT 'ActorHelper Actor Token',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by VARCHAR(100) NOT NULL COMMENT 'ActorHelper Actor Token',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by VARCHAR(100) NOT NULL COMMENT 'ActorHelper Actor Token',
    deleted_at DATETIME NULL,
    deleted_by VARCHAR(100) NULL COMMENT 'ActorHelper Actor Token',
    PRIMARY KEY (id),
    KEY idx_client_tax_profile_period (client_id,effective_from,effective_to),
    KEY idx_client_tax_profile_codes (taxpayer_entity_type,residency_status,income_recipient_type,withholding_policy_code,verification_status),
    CONSTRAINT fk_client_tax_profile_client FOREIGN KEY (client_id) REFERENCES system_clients(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_client_tax_profile_period CHECK (effective_to IS NULL OR effective_to>=effective_from),
    CONSTRAINT chk_client_tax_profile_verification CHECK (
        (verification_status='VERIFIED' AND verified_at IS NOT NULL AND verified_by IS NOT NULL)
        OR verification_status<>'VERIFIED'
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='거래처 세무 적격성 유효기간 SSOT';

DELIMITER $$
CREATE TRIGGER trg_client_tax_profile_no_overlap_insert
BEFORE INSERT ON system_client_tax_profiles FOR EACH ROW
BEGIN
    IF NOT EXISTS(SELECT 1 FROM system_codes WHERE code_group='TAXPAYER_ENTITY_TYPE' AND code=NEW.taxpayer_entity_type AND is_active=1)
       OR NOT EXISTS(SELECT 1 FROM system_codes WHERE code_group='RESIDENCY_STATUS' AND code=NEW.residency_status AND is_active=1)
       OR NOT EXISTS(SELECT 1 FROM system_codes WHERE code_group='INCOME_RECIPIENT_TYPE' AND code=NEW.income_recipient_type AND is_active=1)
       OR NOT EXISTS(SELECT 1 FROM system_codes WHERE code_group='WITHHOLDING_POLICY' AND code=NEW.withholding_policy_code AND is_active=1)
       OR NOT EXISTS(SELECT 1 FROM system_codes WHERE code_group='CLIENT_TAX_PROFILE_VERIFICATION' AND code=NEW.verification_status AND is_active=1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='거래처 세무 프로필 코드값이 올바르지 않습니다.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM system_client_tax_profiles p
        WHERE p.client_id=NEW.client_id AND p.deleted_at IS NULL
          AND NEW.effective_from<=COALESCE(p.effective_to,'9999-12-31')
          AND COALESCE(NEW.effective_to,'9999-12-31')>=p.effective_from
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='거래처 세무 프로필 유효기간이 중복됩니다.';
    END IF;
END$$
CREATE TRIGGER trg_client_tax_profile_no_overlap_update
BEFORE UPDATE ON system_client_tax_profiles FOR EACH ROW
BEGIN
    IF NOT EXISTS(SELECT 1 FROM system_codes WHERE code_group='TAXPAYER_ENTITY_TYPE' AND code=NEW.taxpayer_entity_type AND is_active=1)
       OR NOT EXISTS(SELECT 1 FROM system_codes WHERE code_group='RESIDENCY_STATUS' AND code=NEW.residency_status AND is_active=1)
       OR NOT EXISTS(SELECT 1 FROM system_codes WHERE code_group='INCOME_RECIPIENT_TYPE' AND code=NEW.income_recipient_type AND is_active=1)
       OR NOT EXISTS(SELECT 1 FROM system_codes WHERE code_group='WITHHOLDING_POLICY' AND code=NEW.withholding_policy_code AND is_active=1)
       OR NOT EXISTS(SELECT 1 FROM system_codes WHERE code_group='CLIENT_TAX_PROFILE_VERIFICATION' AND code=NEW.verification_status AND is_active=1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='거래처 세무 프로필 코드값이 올바르지 않습니다.';
    END IF;
    IF NEW.deleted_at IS NULL AND EXISTS (
        SELECT 1 FROM system_client_tax_profiles p
        WHERE p.client_id=NEW.client_id AND p.id<>NEW.id AND p.deleted_at IS NULL
          AND NEW.effective_from<=COALESCE(p.effective_to,'9999-12-31')
          AND COALESCE(NEW.effective_to,'9999-12-31')>=p.effective_from
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='거래처 세무 프로필 유효기간이 중복됩니다.';
    END IF;
END$$
DELIMITER ;
