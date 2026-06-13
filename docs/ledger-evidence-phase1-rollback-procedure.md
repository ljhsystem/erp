# Ledger Evidence Phase1 롤백 절차서

## 1. 목적

본 문서는 Phase1 신규 증빙 구조 적용 실패 시, 기존 구조 중심으로 안전하게 원복하기 위한 롤백 절차를 정의한다.

- 대상: 테스트 DB 우선
- 원칙: 기존 테이블 유지, 신규 테이블 비활성화/제거 중심
- 주의: 운영 DB 직접 적용 금지

## 2. 롤백 대상

- `ledger_evidence_number_sequences`
- `ledger_evidence_number_histories`
- `ledger_evidence_bank`
- `ledger_evidence_tax_invoice`
- `ledger_evidence_tax_invoice_items`
- `ledger_evidence_cash_receipt`
- `ledger_evidence_card_purchase`
- `ledger_evidence_links`
- `ledger_evidence_processing`
- `ledger_evidence_processing_logs`

기존 유지 테이블(삭제/변경 금지):

- `ledger_data_evidences`
- `ledger_bank_transactions`
- `ledger_data_evidence_links`
- `ledger_processing_items`
- `ledger_processing_item_actions`

## 3. 롤백 트리거 기준

아래 중 하나 이상 발생 시 롤백 검토:

1. 증빙 조회/저장 핵심 기능 장애가 30분 이상 지속
2. `evidence_sort_no` 채번 충돌 또는 중복 저장 발생
3. 세금계산서 품목(`ledger_evidence_tax_invoice_items`) 연결 불일치 다수 발생
4. 생성센터 연계 처리(`ledger_evidence_processing`)에서 복구 불가 오류 반복 발생

## 4. 사전 백업

1. 테스트 DB 스냅샷 백업 수행
2. 신규 10개 테이블 row count 백업
3. 롤백 시작 시각/담당자/대상 배포 버전 기록

## 5. 롤백 실행 순서 (테스트 DB)

1. 신규 구조 사용 화면/배치/잡 중지
2. 신규 테이블 대상 데이터 롤백 SQL 실행  
   - `app/migrations/20260601_04_backfill_ledger_evidence_phase1_data.down.sql`
3. 신규 스키마 롤백 SQL 실행  
   - `app/migrations/20260601_03_create_ledger_evidence_phase1_tables.down.sql`
4. 기존 구조 경로 재활성화
5. 핵심 기능 스모크 테스트 재수행

## 6. 검증 체크리스트

1. 신규 10개 테이블 제거 여부 확인
2. 기존 테이블 row count 변동 없음 확인
3. 기존 증빙원본 화면 정상 조회/등록 확인
4. 생성센터 기존 흐름 정상 확인
5. 오류 로그 급증 없음 확인

## 7. 롤백 후 조치

1. 실패 원인 분류  
   - 스키마 문제 / 이관 로직 문제 / 애플리케이션 연동 문제
2. 재시도 전 필수 보완사항 확정
3. 재배포 조건(체크리스트 통과 기준) 문서화

