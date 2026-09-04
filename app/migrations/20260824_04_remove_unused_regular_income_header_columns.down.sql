ALTER TABLE institution_regular_employment_incomes
  ADD COLUMN correction_of_id varchar(36) NULL COMMENT '정정 원 승인문서' AFTER current_approval_request_id,
  ADD COLUMN revision_no int unsigned NOT NULL DEFAULT 1 COMMENT '정정개정번호' AFTER correction_of_id,
  ADD COLUMN request_key varchar(100) NULL COMMENT '멱등요청키' AFTER calculation_source_code,
  ADD COLUMN snapshot_at datetime NULL COMMENT '승인스냅샷 확정일시' AFTER approved_at,
  ADD CONSTRAINT fk_regular_income_correction FOREIGN KEY(correction_of_id) REFERENCES institution_regular_employment_incomes(id),
  ADD CONSTRAINT chk_regular_income_revision CHECK(revision_no>=1),
  ADD UNIQUE KEY uk_regular_income_request(request_key),
  ADD KEY idx_regular_income_correction(correction_of_id,revision_no);
