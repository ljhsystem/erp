# 회계관리 자산관리 Closure

## 최종 구조

- 자산대장: `ledger_assets`
- 자산이동: `ledger_asset_assignments`
- 감가상각: `ledger_asset_depreciations`
- 자산처분: `ledger_asset_disposals`
- 취득·감가상각·처분 회계원본: 기존 `ledger_vouchers`, `ledger_voucher_lines`, `ledger_voucher_line_refs`

## 회계 반영 계약

- 자산등록은 취득 전표를 생성하고 같은 업무 트랜잭션에서 `POSTED`까지 전기한다.
- 감가상각은 자산별 월 중복을 차단하고 취득가액에서 잔존가액을 제외한 상각한도 안에서 전기한다.
- 처분은 취득가액 제거, 감가상각누계액 제거, 처분대금과 처분손익을 하나의 균형 전표로 전기한다.
- 자산이동은 금액을 변경하지 않고 사업구분·프로젝트·팀·책임직원·장소 이력만 기록한다.
- 장부와 재무제표는 기존 `POSTED/CLOSED` 전표 조회를 사용하므로 자산 전기 직후 별도 동기화 없이 반영된다.

## Runtime 검증

- 자산 취득 및 취득 전기: PASS
- 최초 배치와 자산이동 이력: PASS
- 정액법 감가상각 및 전기: PASS
- 자산처분 및 처분 전기: PASS
- 전체 Rollback과 원본 건수 보존: PASS
- DB Comment 누락: 0건
- DB Trigger: 0건
- Route·Permission Metadata: PASS
- Service Logging: PASS
