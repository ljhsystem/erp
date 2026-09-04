# 일용근로소득 Workday 근로시간·비과세 근거 저장계약

> 2026-08-28 변경: 비과세 적용에는 Workday의 `non_taxable_reason`만 사용한다. 별도 비과세 근거자료 문자열 컬럼·화면 필드·Excel 필드는 폐기하며, 이 문서 아래의 Attachment·Revision 구상은 현재 런타임 계약이 아니다.

## 판정

- `20260827_18_add_daily_income_actual_work_minutes`는 2026-08-28 운영 DB에 승인 적용했다. `_15`·`_16`·`_17` 및 아래 후속 Migration은 계속 운영 미적용 상태다.
- 실제 근로시간의 물리·API SSOT는 분 단위 정수 `actual_work_minutes`로 확정한다. 시간 표시는 `actual_work_hours_display` 읽기 전용 Projection이다.
- 기존 Line은 Workday FK의 NULL 여부로 Item 전체와 특정 Workday를 구분할 수 있고 `final_amount`에 부호 있는 금액을 보존할 수 있다. 그러나 과세구분, 적용기간, Revision FK, NULL 범위 UNIQUE가 없어 보강이 필요하다.
- 공용 Attachment SSOT는 현재 없다. `ledger_transaction_files`와 자격·교육의 `attachment_path`는 파일경로 중심의 화면 소유 구조이므로 재사용하지 않는다.
- `_18` 외 아래 후속 Migration은 승인 전 운영 DB에 적용하지 않는다.

## `_18_add_daily_income_actual_work_minutes`

### Up SQL

```sql
ALTER TABLE institution_daily_employment_income_workdays
  ADD COLUMN actual_work_minutes SMALLINT UNSIGNED NULL
    COMMENT '실제 근로시간(분), NULL은 과거자료 미확인' AFTER work_date,
  ADD CONSTRAINT ck_daily_workday_actual_minutes
    CHECK (actual_work_minutes IS NULL OR actual_work_minutes BETWEEN 1 AND 1440);
```

- 부동소수점은 사용하지 않는다.
- 신규 정상입력은 Service에서 `1~1440분`을 필수 검증한다. `0`은 근무 Workday와 모순되므로 허용하지 않는다.
- 1분 단위 Picker 값을 그대로 저장한다. PHP와 JavaScript 모두 계산은 정수 분으로 수행하고 표시만 소수 시간으로 변환한다.
- 기존 행은 모두 NULL로 유지해 과거 미확인을 0시간으로 위장하지 않는다.
- 단일 Workday 조회 이외의 검색키가 아니므로 별도 인덱스를 추가하지 않는다.
- 실제근로시간은 휴게시간을 제외한 실제 근로 분이며 소정근로시간·휴게시간·연장·야간·휴일근로·임금 계산용 `work_quantity`와 구분한다.
- 동일 근로자·동일 근무일의 문서 전체 Group 합계는 1,440분 이하여야 한다. 복수 Group은 구조적으로 허용하되 시간대 중복 여부를 확인하도록 경고한다.

### Down SQL

```sql
ALTER TABLE institution_daily_employment_income_workdays
  DROP CHECK ck_daily_workday_actual_minutes,
  DROP COLUMN actual_work_minutes;
```

## `_19_create_daily_income_non_taxable_revisions`

### Revision Up SQL

```sql
CREATE TABLE institution_daily_employment_income_non_taxable_revisions (
  id VARCHAR(36) NOT NULL,
  daily_employment_income_id VARCHAR(36) NOT NULL,
  daily_employment_income_item_id VARCHAR(36) NOT NULL,
  daily_employment_income_workday_id VARCHAR(36) NULL,
  revision_no INT UNSIGNED NOT NULL,
  non_taxable_item_code VARCHAR(50) NOT NULL,
  applied_amount DECIMAL(18,2) NOT NULL,
  effective_from DATE NULL,
  effective_to DATE NULL,
  application_reason VARCHAR(1000) NOT NULL,
  legal_basis TEXT NOT NULL,
  calculation_details TEXT NOT NULL,
  statutory_standard_id CHAR(36) NOT NULL,
  confirmation_status_code VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
  confirmed_by VARCHAR(100) NULL,
  confirmed_at DATETIME NULL,
  corrects_revision_id VARCHAR(36) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by VARCHAR(100) NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by VARCHAR(100) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_daily_non_tax_revision_no (daily_employment_income_item_id, revision_no),
  KEY idx_daily_non_tax_revision_header (daily_employment_income_id, confirmation_status_code),
  KEY idx_daily_non_tax_revision_period (daily_employment_income_item_id, effective_from, effective_to),
  CONSTRAINT fk_daily_non_tax_revision_header FOREIGN KEY (daily_employment_income_id)
    REFERENCES institution_daily_employment_incomes(id),
  CONSTRAINT fk_daily_non_tax_revision_item FOREIGN KEY (daily_employment_income_item_id)
    REFERENCES institution_daily_employment_income_items(id),
  CONSTRAINT fk_daily_non_tax_revision_workday FOREIGN KEY (daily_employment_income_workday_id)
    REFERENCES institution_daily_employment_income_workdays(id),
  CONSTRAINT fk_daily_non_tax_revision_standard FOREIGN KEY (statutory_standard_id)
    REFERENCES system_statutory_standards(id),
  CONSTRAINT fk_daily_non_tax_revision_correction FOREIGN KEY (corrects_revision_id)
    REFERENCES institution_daily_employment_income_non_taxable_revisions(id),
  CONSTRAINT ck_daily_non_tax_revision_amount CHECK (applied_amount > 0),
  CONSTRAINT ck_daily_non_tax_revision_scope CHECK (
    (daily_employment_income_workday_id IS NOT NULL AND effective_from IS NULL AND effective_to IS NULL)
    OR (daily_employment_income_workday_id IS NULL AND effective_from IS NOT NULL AND effective_to IS NOT NULL AND effective_from <= effective_to)
  ),
  CONSTRAINT ck_daily_non_tax_revision_status CHECK
    (confirmation_status_code IN ('DRAFT','CONFIRMED','CORRECTED','CANCELLED')),
  CONSTRAINT ck_daily_non_tax_revision_confirmation CHECK (
    (confirmation_status_code='DRAFT' AND confirmed_by IS NULL AND confirmed_at IS NULL)
    OR (confirmation_status_code<>'DRAFT' AND confirmed_by IS NOT NULL AND confirmed_at IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

### Line 보강 Up SQL

```sql
ALTER TABLE institution_daily_employment_income_lines
  DROP INDEX uq_daily_income_line,
  ADD COLUMN taxability_code VARCHAR(20) NOT NULL DEFAULT 'NOT_APPLICABLE' AFTER line_type_code,
  ADD COLUMN non_taxable_revision_id VARCHAR(36) NULL AFTER statutory_standard_id,
  ADD COLUMN effective_from DATE NULL AFTER non_taxable_revision_id,
  ADD COLUMN effective_to DATE NULL AFTER effective_from,
  ADD COLUMN workday_scope_key VARCHAR(36)
    GENERATED ALWAYS AS (COALESCE(daily_employment_income_workday_id, 'ITEM')) STORED,
  ADD COLUMN revision_scope_key VARCHAR(36)
    GENERATED ALWAYS AS (COALESCE(non_taxable_revision_id, 'BASE')) STORED,
  ADD UNIQUE KEY uq_daily_income_line_scope
    (daily_employment_income_item_id, workday_scope_key, line_type_code, line_code, revision_scope_key),
  ADD KEY idx_daily_income_line_revision (non_taxable_revision_id),
  ADD CONSTRAINT fk_daily_income_line_non_tax_revision FOREIGN KEY (non_taxable_revision_id)
    REFERENCES institution_daily_employment_income_non_taxable_revisions(id),
  ADD CONSTRAINT ck_daily_income_line_taxability CHECK
    (taxability_code IN ('TAXABLE','NON_TAXABLE','NOT_APPLICABLE')),
  ADD CONSTRAINT ck_daily_income_line_period CHECK
    ((effective_from IS NULL AND effective_to IS NULL) OR
     (effective_from IS NOT NULL AND effective_to IS NOT NULL AND effective_from <= effective_to)),
  ADD CONSTRAINT ck_daily_income_line_non_tax_revision CHECK
    ((taxability_code='NON_TAXABLE' AND non_taxable_revision_id IS NOT NULL)
     OR (taxability_code<>'NON_TAXABLE' AND non_taxable_revision_id IS NULL));
```

- Item 전체 비과세는 Workday FK NULL과 적용기간을 사용한다.
- 특정 Workday는 Workday FK를 사용한다.
- 여러 날짜 배분은 같은 Revision을 참조하는 Workday별 Line으로 기록한다.
- 취소·정정은 기존 Revision UPDATE가 아니라 `corrects_revision_id`를 가진 새 Revision과 반대 부호 Line을 생성한다.
- 승인완료 Revision과 연결 Line은 Service에서 UPDATE·DELETE를 금지한다.
- `applied_amount`와 해당 Revision Line 합계는 1원까지 일치해야 한다.

### 권한 Seed

```sql
INSERT INTO auth_permissions
  (id,sort_no,page,permission_source,category,permission_key,permission_name,description,page_key,is_active,created_at,created_by,updated_at,updated_by)
SELECT UUID(),(SELECT COALESCE(MAX(p.sort_no),0)+1 FROM auth_permissions p),'일용근로소득','ROUTE','대외기관업무',
  'api.institution.income_data.daily_employment.non_taxable_confirm','비과세 확인',
  '일용근로소득 비과세 Revision 확인·정정','web.institution.income_data.daily_employment',1,
  NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
WHERE NOT EXISTS (
  SELECT 1 FROM auth_permissions p
  WHERE p.permission_key='api.institution.income_data.daily_employment.non_taxable_confirm'
);
```

역할 자동 부여는 하지 않는다. 최고관리자 또는 사용자가 승인한 세무처리 역할에 별도 배정한다.

### Down 영향

- 권한 매핑과 권한을 제거한다.
- Line의 신규 FK·CHECK·인덱스·생성열·범위열을 제거하고 기존 UNIQUE를 복원한다.
- Revision 테이블을 제거한다.
- Revision 자료가 존재하면 Down은 데이터 손실이므로 운영에서는 forward correction을 우선한다.

## `_20_create_common_attachment_ssot`

### Attachment 원본 Up SQL

```sql
CREATE TABLE system_attachments (
  id VARCHAR(36) NOT NULL,
  original_file_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(150) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL,
  sha256_hash CHAR(64) NOT NULL,
  storage_object_key VARCHAR(500) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by VARCHAR(100) NOT NULL,
  deleted_at DATETIME NULL,
  deleted_by VARCHAR(100) NULL,
  restored_at DATETIME NULL,
  restored_by VARCHAR(100) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_system_attachment_storage_key (storage_object_key),
  KEY idx_system_attachment_hash (sha256_hash, file_size),
  KEY idx_system_attachment_deleted (deleted_at),
  CONSTRAINT ck_system_attachment_size CHECK (file_size > 0),
  CONSTRAINT ck_system_attachment_hash CHECK (sha256_hash REGEXP '^[0-9a-f]{64}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

- 해시는 중복 탐지 수단이지 전역 UNIQUE가 아니다. 같은 파일의 독립 업무보존을 허용한다.
- Storage Adapter만 객체키를 해석하며 업무테이블은 경로나 임시 URL을 저장하지 않는다.

### 비과세 Revision Attachment Link Up SQL

```sql
CREATE TABLE institution_daily_income_non_tax_revision_attachments (
  id VARCHAR(36) NOT NULL,
  non_taxable_revision_id VARCHAR(36) NOT NULL,
  attachment_id VARCHAR(36) NOT NULL,
  sort_no INT UNSIGNED NOT NULL DEFAULT 0,
  linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  linked_by VARCHAR(100) NOT NULL,
  deleted_at DATETIME NULL,
  deleted_by VARCHAR(100) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_daily_non_tax_revision_attachment (non_taxable_revision_id, attachment_id),
  KEY idx_daily_non_tax_attachment (attachment_id, deleted_at),
  CONSTRAINT fk_daily_non_tax_attachment_revision FOREIGN KEY (non_taxable_revision_id)
    REFERENCES institution_daily_employment_income_non_taxable_revisions(id),
  CONSTRAINT fk_daily_non_tax_attachment_file FOREIGN KEY (attachment_id)
    REFERENCES system_attachments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

- 대상 Type은 테이블 자체가 `DAILY_INCOME_NON_TAXABLE_REVISION`으로 고정한다. 다형 target_id로 FK를 포기하지 않는다.
- 확인된 Revision의 Link는 삭제·교체하지 않고 정정 Revision에 새 Link를 생성한다.
- Attachment 원본은 활성 Link가 하나라도 있으면 영구삭제하지 않는다.

## 예상 운영 변경량

- 2026-08-27 읽기 전용 운영 Preflight: Group 0건, Item 0건, Workday 0건, Line 0건, 중복 Group 작업자 0건이다.
- `_18`: Workday 0건에 nullable 컬럼 메타 추가. 기존 값 갱신 0건.
- `_19`: 신규 Revision 0건, 전환할 기존 Line 0건, 권한 4건을 추가하도록 준비했다. 역할 매핑은 기존 페이지 권한과 `super_admin` 역할 SSOT를 사용한다.
- `_20`: 신규 Attachment와 Link 모두 0건.
- 실제 기존 Workday·Line 행 수와 제약 충돌은 적용 직전 Preflight에서 다시 산출한다.

## UI SSOT

| UI 컬럼 | 공식 SSOT |
|---|---|
| 실제 근로시간 | Workday `actual_work_minutes` → 시간 표시 Projection |
| 과세증감 | `taxability_code='TAXABLE'` Line 합계 |
| 비과세증감 | 확인된 Revision을 참조하는 `NON_TAXABLE` Line 합계 |
| 비과세 적용사유 | 최신 유효 확인 Revision |
| 근거자료 | Revision Attachment Link → `system_attachments` |
| 지급액 | Workday 기본금액과 Line을 서버에서 재계산한 Snapshot |

## 적용 전 Fixture

- 분 단위 근로시간과 NULL 과거자료
- 동일 근로자·동일 날짜·복수 Group의 합산 시간이 1,440분을 넘는 경우 차단
- Workday·Item 기간 비과세와 다일 배분
- 확인·정정·취소 Revision 선형성
- Revision 금액과 Line 합계 1원 대사
- 첨부 다건, 첨부 없는 확인 차단, 승인 후 Link 해제 차단
- 저장·조회·수정·휴지통 복구 및 Transaction Rollback 잔존 0건
