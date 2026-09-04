# 법정기준·사업소득 Trigger 제거 감사 기록

## 결정

운영 DB의 법정기준·사업소득 Trigger 10개를 제거하고 업무 무결성을 명시적 Application Service, Transaction Lock 및 기존 FK·UNIQUE·CHECK로 이전한다. Trigger 자동 복구 Down은 차단하며 사용자 사전승인 없는 재도입을 금지한다.

## 제거 객체와 책임 이전

| Trigger | 대상 | Timing/Event | 기존 책임 | 명시적 대체 책임 |
|---|---|---|---|---|
| `trg_client_tax_profile_no_overlap_insert` | `system_client_tax_profiles` | BEFORE INSERT | 코드·기간 중복 차단 | `BusinessIncomeTaxProfileService::save`, 거래처 행 `FOR UPDATE` |
| `trg_client_tax_profile_no_overlap_update` | `system_client_tax_profiles` | BEFORE UPDATE | 코드·자기 제외 기간 중복 | 같은 Service/Lock |
| `trg_business_income_evidence_canonical_insert` | `ledger_evidence_business_income` | BEFORE INSERT | Canonical 강제 | `BusinessIncomeEvidenceCanonicalPolicy`와 생성 Service |
| `trg_statutory_supersession_bi` | `system_statutory_standard_supersessions` | BEFORE INSERT | Type·Scope·cycle | Correction Transaction과 `StatutoryStandardSupersessionModel::create` |
| `trg_statutory_supersession_bu` | 위와 같음 | BEFORE UPDATE | 관계 불변 | 공개 변경 경로 없음, Model 책임 |
| `trg_statutory_supersession_bd` | 위와 같음 | BEFORE DELETE | 관계 불변 | 공개 변경 경로 없음, Model 책임 |
| `trg_statutory_standard_bu` | `system_statutory_standards` | BEFORE UPDATE | Revision 불변 | Service/Model 직접 수정 차단 |
| `trg_statutory_standard_bd` | 위와 같음 | BEFORE DELETE | Revision 불변 | Service/Model/Route/UI 삭제 차단 |
| `trg_statutory_standard_source_bu` | `system_statutory_standard_sources` | BEFORE UPDATE | Source 불변 | Source Model 기존행 변경 차단 |
| `trg_statutory_standard_source_bd` | 위와 같음 | BEFORE DELETE | Source 불변 | Source Model 기존행 삭제 차단 |

운영 `DEFINER`는 10개 모두 동일한 운영 계정 범위였으며, 생성 시각은 Supersession 3개 `2026-09-03 11:27:16~17`, Revision/Source 4개 `11:31:13~14`, 세무 프로필 2개 `12:24:26~27`, Evidence 1개 `12:24:36`이다. 보안상 계정명은 문서에 복제하지 않는다.

## 삭제 전 SHOW CREATE TRIGGER 정의

아래 SQL은 삭제 직전 `information_schema.TRIGGERS.ACTION_STATEMENT`와 기존 확정 Migration을 대조한 전체 정의다. 원본은 각각 `20260903_01`, `05`, `10`, `12`에 보존되어 있다.

```sql
CREATE TRIGGER trg_client_tax_profile_no_overlap_insert BEFORE INSERT ON system_client_tax_profiles FOR EACH ROW
BEGIN
 IF NOT EXISTS(SELECT 1 FROM system_codes WHERE code_group='TAXPAYER_ENTITY_TYPE' AND code=NEW.taxpayer_entity_type AND is_active=1)
 OR NOT EXISTS(SELECT 1 FROM system_codes WHERE code_group='RESIDENCY_STATUS' AND code=NEW.residency_status AND is_active=1)
 OR NOT EXISTS(SELECT 1 FROM system_codes WHERE code_group='INCOME_RECIPIENT_TYPE' AND code=NEW.income_recipient_type AND is_active=1)
 OR NOT EXISTS(SELECT 1 FROM system_codes WHERE code_group='WITHHOLDING_POLICY' AND code=NEW.withholding_policy_code AND is_active=1)
 OR NOT EXISTS(SELECT 1 FROM system_codes WHERE code_group='CLIENT_TAX_PROFILE_VERIFICATION' AND code=NEW.verification_status AND is_active=1)
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='거래처 세무 프로필 코드값이 올바르지 않습니다.'; END IF;
 IF EXISTS(SELECT 1 FROM system_client_tax_profiles p WHERE p.client_id=NEW.client_id AND p.deleted_at IS NULL AND NEW.effective_from<=COALESCE(p.effective_to,'9999-12-31') AND COALESCE(NEW.effective_to,'9999-12-31')>=p.effective_from)
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='거래처 세무 프로필 유효기간이 중복됩니다.'; END IF;
END;

CREATE TRIGGER trg_client_tax_profile_no_overlap_update BEFORE UPDATE ON system_client_tax_profiles FOR EACH ROW
BEGIN
 IF NOT EXISTS(SELECT 1 FROM system_codes WHERE code_group='TAXPAYER_ENTITY_TYPE' AND code=NEW.taxpayer_entity_type AND is_active=1)
 OR NOT EXISTS(SELECT 1 FROM system_codes WHERE code_group='RESIDENCY_STATUS' AND code=NEW.residency_status AND is_active=1)
 OR NOT EXISTS(SELECT 1 FROM system_codes WHERE code_group='INCOME_RECIPIENT_TYPE' AND code=NEW.income_recipient_type AND is_active=1)
 OR NOT EXISTS(SELECT 1 FROM system_codes WHERE code_group='WITHHOLDING_POLICY' AND code=NEW.withholding_policy_code AND is_active=1)
 OR NOT EXISTS(SELECT 1 FROM system_codes WHERE code_group='CLIENT_TAX_PROFILE_VERIFICATION' AND code=NEW.verification_status AND is_active=1)
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='거래처 세무 프로필 코드값이 올바르지 않습니다.'; END IF;
 IF NEW.deleted_at IS NULL AND EXISTS(SELECT 1 FROM system_client_tax_profiles p WHERE p.client_id=NEW.client_id AND p.id<>NEW.id AND p.deleted_at IS NULL AND NEW.effective_from<=COALESCE(p.effective_to,'9999-12-31') AND COALESCE(NEW.effective_to,'9999-12-31')>=p.effective_from)
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='거래처 세무 프로필 유효기간이 중복됩니다.'; END IF;
END;

CREATE TRIGGER trg_business_income_evidence_canonical_insert BEFORE INSERT ON ledger_evidence_business_income FOR EACH ROW
BEGIN
 IF NEW.source_type<>'INTERNAL_APPROVAL' OR NEW.import_type<>'BUSINESS_INCOME_REPORT' OR NEW.transaction_direction<>'OUT' OR NEW.operation_type<>'BUSINESS_INCOME' OR NEW.employee_id IS NOT NULL
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='신규 사업소득 Evidence Canonical 값이 올바르지 않습니다.'; END IF;
END;

CREATE TRIGGER trg_statutory_supersession_bu BEFORE UPDATE ON system_statutory_standard_supersessions FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='확정된 법정기준 Revision 대체 관계는 수정할 수 없습니다.'; END;
CREATE TRIGGER trg_statutory_supersession_bd BEFORE DELETE ON system_statutory_standard_supersessions FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='확정된 법정기준 Revision 대체 관계는 삭제할 수 없습니다.'; END;
CREATE TRIGGER trg_statutory_standard_bu BEFORE UPDATE ON system_statutory_standards FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='확정된 법정기준 Revision은 수정할 수 없습니다. 신규 정정 Revision을 등록하세요.'; END;
CREATE TRIGGER trg_statutory_standard_bd BEFORE DELETE ON system_statutory_standards FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='확정된 법정기준 Revision은 삭제할 수 없습니다.'; END;
CREATE TRIGGER trg_statutory_standard_source_bu BEFORE UPDATE ON system_statutory_standard_sources FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='확정된 법정기준 Source는 수정할 수 없습니다. 신규 Revision에 Source를 등록하세요.'; END;
CREATE TRIGGER trg_statutory_standard_source_bd BEFORE DELETE ON system_statutory_standard_sources FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='확정된 법정기준 Source는 삭제할 수 없습니다.'; END;
```

`trg_statutory_supersession_bi`의 전체 본문은 길이와 관계없이 삭제 전 정의 그대로 확정 Migration `app/migrations/20260903_10_create_statutory_standard_supersessions.up.sql`의 동명 `CREATE TRIGGER` 블록에 보존한다. 이 정의는 자기참조, 양 Revision `FOR UPDATE`, Type·Scope 동일성 및 최대 1000단계 cycle 검사를 포함한다.

## 검증과 한계

- 격리 MariaDB 10.11.11: Trigger 10 → 0, Fixture 제거 PASS.
- 운영: Trigger 10 → 0, 11개 업무 테이블 수·업무행 수·법정기준 3종 hash·FK 수 불변.
- 사업소득 Closure: Evidence/Raw Line/Transaction/Item/Settlement/Link/Closure 생성, 재호출 무중복, 실패 rollback PASS.
- DB 관리자가 직접 SQL을 수행하면 Application Validation을 우회할 수 있다. FK·UNIQUE·CHECK가 맡는 구조 무결성과 달리 코드 유효성·기간 중복·Canonical·불변 정책은 공식 Service 경로 및 운영권한 통제가 필요하다.
