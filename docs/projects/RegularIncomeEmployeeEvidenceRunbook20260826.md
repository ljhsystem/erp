# 직원별 급여 증빙 전환 운영 Runbook

## 적용 경계

이 문서는 요청 #8을 승인 전 상태로 단순 원상복구한 뒤 직원별 급여 증빙 구조를 적용하는 절차다. Codex나 운영도구가 승인 API를 호출하거나 결재자를 대행해서는 안 된다. 기관 거래, 지급예정과 전표는 생성하지 않는다.

## 신규 구조 적용과 사용자 재승인

1. 요청과 최종단계가 `pending`, 문서가 `PENDING`이고 대상 증빙·거래·Link·Registry가 0건인지 확인한다.
2. 운영 DB 전체 백업의 파일명, 크기, SHA-256을 기록한다.
3. `20260826_05_enable_employee_salary_report_evidence` 하나만 적용한다.
4. 직원별 증빙 컬럼, FK, 복합 UNIQUE와 Registry의 두 역할 CHECK를 확인한다.
5. 원본 Header·Item·employee 및 결재요청 문서종류·reference 일치 Preflight를 수행한다.
6. 실제 지정 결재자가 UI에서 요청 #8을 한 번만 재승인한다.
7. 읽기 전용으로 증빙 2건, 직원 거래 2건, 거래 Item 8건, Settlement 7건, Link 2건, Registry 4건을 검증한다.
8. 기관 거래, 지급예정, 전표가 각각 0건인지 확인한다.
9. 각 증빙이 업무분류 확인 전 상태인 `CORRECTION_REQUIRED`이며 자기 직원 Item의 Line만 조회되는지 확인한다.

## 복구와 중단

Migration Down은 직원별 증빙 또는 Registry가 하나라도 있으면 차단된다. 운영자가 원상복구 완료로 오해할 수 있는 직접 SQL이나 강제 FK 해제는 금지한다.
