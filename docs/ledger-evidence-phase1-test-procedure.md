# Ledger Evidence 1차 테스트 절차서 (실행 전 검토용)

## 1. 범위
- `ledger_evidence_bank`
- `ledger_evidence_tax_invoice`
- `ledger_evidence_tax_invoice_items`
- `ledger_evidence_cash_receipt`
- `ledger_evidence_card_purchase`
- `ledger_evidence_links`
- `ledger_evidence_processing`
- `ledger_evidence_processing_logs`
- `ledger_evidence_number_sequences`
- `ledger_evidence_number_histories`

## 2. 사전 조건
1. 운영 DB 사용 금지
2. 테스트 DB 별도 준비
3. 전체 백업 수행
4. 신규/기존 테이블 공존 상태 유지

## 3. 실행 순서(테스트 DB)
1. `20260601_03_create_ledger_evidence_phase1_tables.up.sql` 적용
2. 스키마 검증
3. `20260601_04_backfill_ledger_evidence_phase1_data.up.sql` 적용
4. 데이터 정합성 검증
5. 화면/API 읽기 검증
6. 롤백 SQL 리허설

## 4. 검증 항목
1. 테이블 생성 검증
- 10개 신규 테이블 생성 여부
- 인덱스/FK 생성 여부

2. 이관 건수 검증
- 은행: `ledger_bank_transactions` -> `ledger_evidence_bank` 건수
- 세금계산서: `ledger_data_evidences`(TAX_INVOICE 계열) -> `ledger_evidence_tax_invoice` 건수
- 현금영수증: `ledger_data_evidences`(CASH_RECEIPT) -> `ledger_evidence_cash_receipt` 건수
- 카드매입: `ledger_data_evidences`(CARD_HOMETAX/CARD_STATEMENT) -> `ledger_evidence_card_purchase` 건수

3. 필드 매핑 검증
- `external_key`, `source_type`, `evidence_date`, 금액 컬럼, 참조 컬럼
- `evidence_status` 코드값(ACTIVE/DELETED/INVALID) 검증

4. 순번 검증
- `sort_no` 테이블 내부 순번 검증
- `evidence_sort_no` 유일성 검증
- `ledger_evidence_number_sequences.last_evidence_sort_no` 증가 검증
- `ledger_evidence_number_histories` 발급 이력 검증

5. 세금계산서 품목 검증
- `ledger_evidence_tax_invoice_items` 생성/연결 여부
- `tax_invoice_id` FK 무결성 검증

6. Soft Delete 검증
- 신규 테이블 `deleted_at` 정책 확인
- 물리 삭제 미수행 확인

## 5. 샘플 검증 SQL(예시)
```sql
SELECT COUNT(*) FROM ledger_evidence_bank;
SELECT COUNT(*) FROM ledger_evidence_tax_invoice;
SELECT COUNT(*) FROM ledger_evidence_cash_receipt;
SELECT COUNT(*) FROM ledger_evidence_card_purchase;

SELECT evidence_sort_no, COUNT(*)
FROM (
  SELECT evidence_sort_no FROM ledger_evidence_bank
  UNION ALL
  SELECT evidence_sort_no FROM ledger_evidence_tax_invoice
  UNION ALL
  SELECT evidence_sort_no FROM ledger_evidence_cash_receipt
  UNION ALL
  SELECT evidence_sort_no FROM ledger_evidence_card_purchase
) t
GROUP BY evidence_sort_no
HAVING COUNT(*) > 1;
```

## 6. 합격 기준
1. 이관 대상 4유형 건수 오차 0
2. `evidence_sort_no` 중복 0
3. FK 오류 0
4. 필수 컬럼 NULL 오류 0
5. 롤백 리허설 성공
