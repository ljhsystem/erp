# Ledger Evidence Phase1 마이그레이션 설계서 (실행 금지/작성 전용)

## 1. 마이그레이션 순서

1. `ledger_evidence_number_sequences`
2. `ledger_evidence_number_histories`
3. `ledger_evidence_bank`
4. `ledger_evidence_tax_invoice`
5. `ledger_evidence_tax_invoice_items`
6. `ledger_evidence_cash_receipt`
7. `ledger_evidence_card_purchase`
8. `ledger_evidence_links`
9. `ledger_evidence_processing`
10. `ledger_evidence_processing_logs`

## 2. FK 생성 순서

1. 마스터 참조 FK  
   - `ledger_evidence_bank.bank_account_id -> system_bank_accounts.id`  
   - `ledger_evidence_* .client_id -> system_clients.id`  
   - `ledger_evidence_* .project_id -> system_projects.id`
2. 동일 도메인 헤더-라인 FK  
   - `ledger_evidence_tax_invoice_items.tax_invoice_id -> ledger_evidence_tax_invoice.id`
3. 처리 로그 FK  
   - `ledger_evidence_processing_logs.processing_id -> ledger_evidence_processing.id`

## 3. 데이터 이관 순서 (테스트 DB 전용 계획)

1. 시퀀스 초기화(`ledger_evidence_number_sequences`)
2. 은행 데이터 이관
3. 세금계산서 헤더 이관
4. 세금계산서 품목 이관
5. 현금영수증 이관
6. 카드매입 이관
7. `ledger_evidence_number_histories` 이력 적재
8. 건수/합계/NULL 검증

## 4. 기존 -> 신규 컬럼 매핑표 (요약)

### 4.1 은행 (`ledger_bank_transactions`, `ledger_data_evidences` -> `ledger_evidence_bank`)

- `ledger_bank_transactions.id` -> `legacy_bank_transaction_id`
- `ledger_bank_transactions.transaction_date` -> `evidence_date`
- `ledger_bank_transactions.deposit_amount` -> `deposit_amount`
- `ledger_bank_transactions.withdraw_amount` -> `withdraw_amount`
- `ledger_bank_transactions.balance_amount` -> `balance_amount`
- `ledger_bank_transactions.counterparty_name` -> `counterparty_name`
- `ledger_bank_transactions.memo` -> `description`
- `ledger_data_evidences.client_id` -> `client_id`
- `ledger_data_evidences.project_id` -> `project_id`
- `ledger_data_evidences.source_key/source_ref` 성격 값 -> `external_key`

### 4.2 세금계산서 (`ledger_data_evidences` -> `ledger_evidence_tax_invoice`)

- `evidence_date/date` -> `evidence_date`
- `approval_number` -> `external_key`
- `supplier_*` -> `supplier_name`, `supplier_business_no`
- `buyer_*` -> `buyer_name`, `buyer_business_no`
- `supply_amount` -> `supply_amount`
- `vat_amount` -> `vat_amount`
- `total_amount` -> `total_amount`
- `client_id`, `project_id` -> 동일

### 4.3 세금계산서 품목 (`ledger_data_evidences.item_*` -> `ledger_evidence_tax_invoice_items`)

- `item_date` -> `item_date`
- `item_name` -> `item_name`
- `item_spec` -> `item_spec`
- `item_qty` -> `item_qty`
- `item_price` -> `item_price`
- `item_supply_amount` -> `item_supply_amount`
- `item_vat_amount` -> `item_vat_amount`
- `item_note` -> `item_note`

### 4.4 현금영수증 (`ledger_data_evidences` -> `ledger_evidence_cash_receipt`)

- `evidence_date/date` -> `evidence_date`
- `approval_number` -> `external_key`
- `supply_amount` -> `supply_amount`
- `vat_amount` -> `vat_amount`
- `service_amount` -> `service_amount`
- `total_amount` -> `total_amount`
- `client_id`, `project_id` -> 동일

### 4.5 카드매입 (`ledger_data_evidences` -> `ledger_evidence_card_purchase`)

- `evidence_date/date` -> `evidence_date`
- `approval_number` -> `external_key`
- `card_company` -> `card_company_name`
- `card_number` -> `card_number_masked`
- `merchant_name` -> `merchant_name`
- `supply_amount` -> `supply_amount`
- `vat_amount` -> `vat_amount`
- `service_amount` -> `service_amount`
- `total_amount` -> `total_amount`
- `client_id`, `project_id` -> 동일

## 5. 화면 전환 순서 (1차)

1. 입출금(은행) 화면 조회/등록 경로를 `ledger_evidence_bank`로 전환
2. 세금계산서 화면을 `ledger_evidence_tax_invoice` + `ledger_evidence_tax_invoice_items`로 전환
3. 현금영수증 화면을 `ledger_evidence_cash_receipt`로 전환
4. 카드매입 화면을 `ledger_evidence_card_purchase`로 전환
5. 링크/처리 상태 조회는 `ledger_evidence_links`, `ledger_evidence_processing` 병행 연결

## 6. 병행 운영 원칙

1. 기존 테이블 삭제 금지
2. 신규/기존 구조 동시 조회 가능 기간 유지
3. 장애 시 신규 경로 비활성화 후 기존 경로 즉시 복귀
4. `ledger_evidence_links` 중복 방지 유니크 정책 적용:
   - 유니크 키: (`evidence_type`, `evidence_id`, `target_type`, `target_id`, `link_type`)
   - Soft Delete 재연결은 신규 INSERT가 아니라 기존 링크 복구(`deleted_at = NULL`) 정책 사용

## 7. evidence_sort_no 무결성 원칙

1. 단일 진실 원천: `ledger_evidence_number_histories.issued_evidence_sort_no`
2. 보강 제약: `issued_evidence_sort_no` UNIQUE
3. 본문 테이블의 `evidence_sort_no`는 보조 저장값으로 사용
