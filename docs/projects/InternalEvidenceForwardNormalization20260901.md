# 내부 업무형 Evidence Forward 정규화 운영계획

> 이 문서는 당시 정규화 작업의 설계·전환 계획이다. 증빙원본의 공통 목적, 컬럼 의미, `raw_*` 규칙과 책임 경계의 최종 SSOT는 [EvidenceOriginalContract.md](../architecture/EvidenceOriginalContract.md)이며, 충돌하는 일반 계약은 폐기된 것으로 본다.

## 운영 원칙

승인 원천 사실과 승인 이력은 유지한다. 신규 컬럼 추가, 결정적 backfill, dual-write, 신규 우선 read, 소비자 전환 순서로 진행하며 운영 DDL·DML은 별도 승인을 받는다. 승인 원천 자체가 달라지는 경우에만 복구·재승인한다.

## Migration 분할

1. 일용: `20260901_01` Header 컬럼 추가 → `_02` 결정적 backfill → `_03` CHECK → `_04` TableSettings key 전환 → `_05` Raw Line 테이블 → `_06` Raw Line backfill.
2. 상용: 기존 `20260901_11~13` 계획은 폐기하고 `20260902_41~42`, `_47` Forward Migration으로 대체한다.
3. 개인경비: 기존 `20260901_21~22` 계획은 폐기하고 `20260902_43~44`, `_47` Forward Migration으로 대체한다.
4. 최종 제거: 구 컬럼별 PHP·JS·SQL·테스트·사전·TableSettings·Export·Search/Sort·Snapshot reader·View/Procedure/Trigger/Event 소비자가 0건이고 운영 전수 대사가 통과한 뒤 별도 Migration으로 작성한다.

부분 적용 상태, 선행 backfill 값, 원천 연결 불일치가 있으면 각 Migration은 자동 진행하지 않고 차단한다. Down이 승인 사실 또는 재구성 Snapshot을 잃는 backfill은 자동 복구하지 않는다.

## 구·신규 호환

| 업무 | 구 스키마 쓰기 | 신규 스키마 쓰기 | 읽기 |
|---|---|---|---|
| 일용 | 구 `total_*` 저장 | 구 `total_*` + 신규 `raw_*` dual-write | 신규 `raw_*` 우선, 구 컬럼 fallback |
| 상용 | 실제 컬럼만 필터하여 구 컬럼 저장 | 구 금액 + 신규 정규화 금액·승인 Snapshot 저장 | 신규 정규화 금액 우선, 구 금액 fallback |
| 개인경비 | 실제 컬럼만 필터하여 기존 저장 | 기존 값 + 승인 추적 3개 저장 | 승인 추적 컬럼 적용 후 직접 조회 |

## 운영 SELECT Dry-run 기준선

- 일용 Evidence 1건: 금액식 불일치 0건, 사업구분 결정 불가 0건.
- 상용 Evidence 2건: 원 Header/Item 연결 또는 gross·deduction 불일치 0건. 두 건 모두 `LEGACY_RECONSTRUCTED` backfill 가능하다.
- 개인경비 Evidence 9건: 승인 연결 결정 불가 0건, `MANUAL_REVIEW_REQUIRED` 0건.
- TableSettings 전환 대상: 일용 1행, 상용 1행. 상용 `raw_employee_count` 소비자 1행은 유지하며 최종 제거 전에 별도 제거한다.

## 정순옥 일용 Transaction Repair 설계

승인 Request·Step·Evidence·Snapshot·Evidence Link는 유지한다. Repair 대상은 Transaction `2d315f38-bfa7-4ca6-8d6d-fb9bbaa50b7c`로만 고정한다. Item ID는 고정하지 않고 실행 직전 전체 Item을 `FOR UPDATE`로 조회하여 정확히 1건인지와 현재 Transaction Hash를 다시 검증한다. Evidence `0f07686f-fb23-4939-8a67-6b7860f192f3`와 Link `9f7d8c47-585b-4a02-b07d-1e2f363fe686`도 같은 실행 Transaction에서 잠금 대사한다.

목표는 `operation_type=DAILY_WORKER`, Item 합계 452,940원, signed Settlement 합계 -2,940원, Final 450,000원이다. 공식식은 `Item 합계 + signed Settlement 합계 = Final`이다. 사용자부담 20,820원은 지급거래에서 제외한다. Link는 정확히 1건을 유지하고 Registry/Closure의 Revision·Source Hash를 대사한다.

Repair 전용 감사 저장소는 다음을 보존해야 한다: 결정적 `request_key UNIQUE`, approval/evidence/transaction ID, before/after JSON Snapshot과 Hash, `processed_at`, `processed_by`. 단일 DB Transaction에서 대상 ID·현재 Hash·부분변경 여부를 잠근 뒤 `ALREADY_REPAIRED` 또는 `PARTIAL_REPAIR_DETECTED`를 결정한다.

실패주입 지점은 8단계다: 감사행 예약 후, Header 변경 후, Item 변경 후, Settlement 생성 후, 금액 대사 후, Registry 대사 후, Closure 대사 후, 감사행 완료 직전. 각 단계에서 전체 Rollback과 재실행 변경 0건을 검증한다.

현재 Dry-run은 금액·Link·Registry·Closure·코드 SSOT·후속 연결 부재를 통과했지만 Repair 감사 저장소가 없어 실행 불가다. 감사 테이블 DDL과 Repair 실행기는 별도 승인 대상이다.

## 승인 전 상태 복구 조건

- 승인 원천 raw 값 수정이 필요하다.
- 계산 입력 또는 법정기준의 소급 변경이 필요하다.
- 신규 필수 승인값을 기존 자료에서 결정할 수 없다.
- 승인 대상자·기간·지급일·금액이 변경된다.
- 기존 승인으로 신규 산출물을 정당화할 수 없다.

컬럼 rename, Snapshot 보강, 조회 변경, 결정적 Projection Repair만으로는 복구·재승인하지 않는다.

## 격리 검증

운영 Schema에서 임시 테이블을 만들지 않는다. 별도 테스트 Schema에 내부 업무 원본, 승인 Request/Step, 세 Evidence, Transaction Header/Item/Settlement, Evidence Link, Registry/Closure, 코드와 TableSettings 최소 Fixture를 복제한다. 개인경비·상용·일용 신규 승인, Callback 재호출, 부분 적용, backfill 불일치, 위 8단계 실패주입을 수행한다. 운영에는 이 문서의 SELECT Dry-run 외 작업을 실행하지 않는다.
