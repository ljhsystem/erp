# 운영 Evidence 상태 기준선 전환

## 범위

공식 Evidence 8개 유형의 미연결 운영 행만 `CORRECTION_REQUIRED`로 전환한다. 연결 Evidence, 삭제 Evidence, 거래·전표·Link, 업무분류정보와 원본 필드는 변경하지 않는다.

## 실행도구

`tools/apply_evidence_status_baseline_transition.php`

- Dry-run: `php tools/apply_evidence_status_baseline_transition.php --output=storage/db_backup/evidence_status_baseline_20260825_dry_run.json`
- Apply: `php tools/apply_evidence_status_baseline_transition.php --apply --snapshot=storage/db_backup/evidence_status_baseline_20260825_dry_run.json`
- 멱등 재검증: Apply 명령을 Snapshot 인자 없이 다시 실행하며 변경건수가 0인지 확인한다.

Dry-run과 Apply는 동일한 Evidence 목록·연결 판정 함수를 사용한다. Apply는 `ActorHelper::system('EVIDENCE_STATUS_BASELINE_20260825')` Actor를 저장하고, 상태가 실제 변경되는 행만 `updated_at`, `updated_by`와 함께 갱신한다.

## 연결 판정

- 거래: 삭제되지 않은 공식 Link, 실제 Transaction 존재, Transaction `deleted_at IS NULL`, 취소·무효·폐기 상태가 아님
- 전표: 삭제되지 않은 공식 Link, 실제 Voucher 존재, Voucher `deleted_at IS NULL`, 상태가 `deleted`가 아님, `is_reversal=0`
- 거래 또는 전표 중 하나라도 유효하면 전환 제외
- 양쪽 연결은 Evidence 고유 1건으로 제외
- 삭제 Link, 고아 Link, 취소·무효 대상 Link, 취소전표는 활성 연결이 아님

## 삭제 Evidence

삭제 Evidence는 이번 상태전환에서 제외한다. 삭제·복원은 `deleted_at/deleted_by`의 독립 생명주기로 유지하며 `evidence_status`를 변경하지 않는다.
