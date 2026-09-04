# 사업소득 금액 Grain 및 DB Comment Closure

## 확정 계약

- Evidence `supply_amount`와 `total_amount`는 공제 전 총지급액이다.
- `vat_amount=0`, `service_amount=0`이다.
- 원본 총지급액·총공제액·최종지급액은 각각 Raw 컬럼에 보존한다.
- Transaction Item과 Transaction Supply는 총지급액, Transaction Final은 최종지급액이다.
- 순액과 법정공제·기타공제의 합은 Evidence 총액과 일치한다.
- 전표·분개·Voucher·Journal·Posting 변경은 없다.

## Comment 적용

- Forward Migration: `app/migrations/20260903_14_complete_database_korean_comments.up.sql`
- 전체 Manifest: `docs/projects/DatabaseCommentSsotManifest20260903.json`
- 정비 대상: 163 Table / 3,104 Column
- 변경: Table Comment 3건 / Column Comment 476건
- 보호영역: 캘린더 3 Table / 83 Column
- DML, Trigger, Procedure, Function, Event: 0건

## 검증 기준

- MariaDB 10.11.11 격리 Schema에서 90개 ALTER 전체 실행
- Comment 제외 컬럼·인덱스 Signature 동일
- 운영 PK·FK·UNIQUE·CHECK·Table·View Signature 동일
- 운영 행 수 동일, Trigger 0건, TableSettings 88건 유지
- 캘린더 행 수·CHECKSUM 동일
- `CHECKSUM TABLE`은 Comment 메타데이터에 따라 변하므로 별도 격리 복제에서 논리 데이터 Hash 동일을 검증
