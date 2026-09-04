# 증빙원본 공용계약 및 정규화 규칙

> 이 문서는 증빙원본 설계·개발·Migration·검토의 최종 Architecture SSOT다. Evidence 관련 테이블, 컬럼, Service, API, 화면 또는 Migration을 변경하기 전에 반드시 확인한다. 구현계획·Migration 문서가 이 문서와 충돌하면 이 문서를 우선한다.

## 1. 존재 목적

증빙원본은 최초 발원자료를 보존하면서 그 자료를 ERP 공용분류로 정규화하여 Transaction, 추천분개, Voucher, 통계와 분석에 활용하기 위한 계층이다.

ERP는 전표만 저장하지 않고 다음 단계를 구분하여 보존한다.

1. 최초 발원자료
2. Evidence 원본
3. Evidence의 정규화된 공용분류
4. 실제 Transaction
5. Voucher
6. Evidence–Transaction–Voucher 연결
7. 생성·수정·삭제·정정 이력

이 계층의 목적은 다음과 같다.

- 최초 사실 재현
- 원본과 가공값 구분
- 안전한 Transaction 생성
- 추천분개 정밀도 향상
- Voucher 근거 추적
- 사업·현장·거래처·직원·계좌·카드·팀별 분석
- 장기간 데이터 축적과 경영분석
- 오류 발생 시 원본부터 전표까지 역추적

## 2. Evidence 3계층

모든 Evidence 본문은 다음 세 영역으로 구성한다.

### A. 공용 정규화 영역

ERP가 자료유형과 업무 의미를 통일하여 검색·분석·후속 처리에 사용하는 영역이다.

### B. 유형별 원본영역

최초 발원자료에 실제 존재했던 사실을 `raw_*` 컬럼과 유형별 Raw Line에 보존하는 영역이다.

### C. 감사영역

생성·수정·삭제 Actor와 시각 및 생명주기를 보존하는 영역이다.

내부 승인형 Evidence는 업무상 필요할 때 승인, 계산 Revision, Snapshot, Hash와 멱등성 추적영역을 추가한다.

## 3. 공용 정규화 영역

각 Evidence 본문 테이블은 다음 공용 물리컬럼을 동일한 이름과 의미로 둔다.

| 컬럼 | 의미 |
|---|---|
| id | Evidence 본문 ID |
| sort_no | 자료유형 내부 표시 순서 |
| external_key | 외부원본 또는 내부 승인원본의 표준 식별값 |
| source_type | 자료출처 |
| import_type | 자료유형 |
| business_unit | 정규화된 사업구분 |
| transaction_direction | 정규화된 거래구분 |
| operation_type | 정규화된 업무유형 |
| client_id | 정규화된 거래처 |
| employee_id | 정규화된 직원 |
| project_id | 정규화된 프로젝트 |
| bank_account_id | 정규화된 계좌 |
| card_id | 정규화된 카드 |
| work_team_id | 정규화된 작업팀 |
| evidence_status | 해당 유형의 공용컬럼 확인·보정 및 활용 준비상태 |
| created_at / created_by | 생성 시각과 Actor |
| updated_at / updated_by | 수정 시각과 Actor |
| deleted_at / deleted_by | 삭제 시각과 Actor |

특정 EvidenceType에서 사용하지 않는 공용참조는 컬럼을 제거하지 않고 NULL을 허용한다.

- 현금영수증에 직원 참조가 적용되지 않으면 `employee_id=NULL`이다.
- 급여에 카드 참조가 적용되지 않으면 `card_id=NULL`이다.
- 본사업무에 프로젝트와 작업팀이 적용되지 않으면 `project_id`, `work_team_id`는 NULL이다.

이 NULL은 컬럼 미구현이 아니라 해당 증빙에 적용되지 않음을 뜻한다. 다만 원천 Grain이나 기존 물리계약 때문에 공용컬럼을 즉시 추가할 수 없는 Legacy 테이블은 Forward Migration으로 전환하며, 전환 완료 전까지 명시적인 Alias 계약을 문서화한다.

기존 물리 테이블의 `team_id` 등 다른 명칭은 레거시 호환 필드로 취급한다. 신규 설계와 최종 정규화 명칭은 `work_team_id`를 사용하며, 기존 소비자 조사와 Forward Migration 없이 즉시 rename 또는 drop하지 않는다.

## 4. 원본영역 `raw_*`

최초 발원자료에 실제 존재했던 사실값은 모두 `raw_*`로 저장한다.

적용 대상:

- 기관 다운로드 파일 원본
- 외부 API 응답 원본
- 수기 증빙 작성값
- 개인경비 신청 Item의 승인된 사실
- 상용근로소득 Item·계산의 승인된 사실
- 일용근로소득 Item·계산의 승인된 사실
- 그 밖의 업무도메인에서 최종승인으로 확정된 원천 사실

명명 규칙:

- 금액: `raw_*_amount`
- 날짜: `raw_*_date`
- 코드: `raw_*_code`
- 명칭: `raw_*_name`
- 구분: `raw_*_type` 또는 `raw_*_category`
- 반복 상세: 유형별 Raw Line 자식테이블

`raw_*`는 외부 업로드 자료에만 적용하는 규칙이 아니다. 내부 업무도메인에서 결재로 확정된 사실에도 동일하게 적용한다.

## 5. 공용값과 원본값의 동시 보존

공용컬럼과 같은 개념이 원본자료에 있어도 두 값을 합치지 않는다.

| 최초 원본값 | ERP 공용 정규화값 |
|---|---|
| raw_business_unit | business_unit |
| raw_transaction_type | transaction_direction |
| raw_operation_type | operation_type |
| raw_client_name 또는 raw_client_code | client_id |
| raw_employee_name 또는 raw_employee_no | employee_id |
| raw_project_name 또는 raw_project_code | project_id |
| raw_bank_name·raw_account_no | bank_account_id |
| raw_card_no·raw_card_name | card_id |
| raw_work_team_name | work_team_id |
| raw_document_no | external_key |

- `raw_*`: 최초 발원자료에 기록된 사실
- 공용컬럼: ERP가 확인·매핑·보정한 정규화 값

현재 두 값이 같더라도 책임이 다르면 함께 보존할 수 있다.

## 6. 원본 불변성

`raw_*`는 원본 보존영역이다. 공용분류를 변경하더라도 원본을 덮어쓰지 않는다.

예를 들어 `raw_business_unit`이 최초 입력값이고 `business_unit`이 확인된 `BUSINESS_UNIT` 코드라면, 재분류 시 `business_unit`만 변경한다. 다음 공용참조도 동일하다.

- transaction_direction
- operation_type
- client_id
- employee_id
- project_id
- bank_account_id
- card_id
- work_team_id

원본 자체를 정정해야 할 때 단순 UPDATE로 과거 원본을 소거하지 않는다. 해당 유형이 가진 삭제·재등록, 정정 Revision 또는 승인정정 계약을 사용하여 이전 원본을 추적 가능하게 보존한다.

## 7. 공용분류 활용 책임

공용컬럼은 사용자가 확인하거나 시스템이 안전하게 매핑한 정규화 데이터다.

활용처:

- 검색·정렬·필터
- 통계와 경영분석
- 사업구분·프로젝트·현장·작업팀 분석
- 거래처·직원 분석
- 계좌·카드 분석
- Transaction 생성
- 추천분개
- Voucher 작성 보조

원본값이 있다는 이유로 공용컬럼을 생략하지 않는다. 공용컬럼이 완성됐다는 이유로 `raw_*`를 삭제하지 않는다.

## 8. Evidence 상태

`evidence_status`는 공용 정규화 영역의 준비상태를 나타낸다.

최소 판단 범위:

- 원본자료 보존 완료 여부
- 해당 EvidenceType의 필수 공용컬럼 확인 여부
- Transaction·추천분개·Voucher 활용 가능 여부
- 추가 확인 또는 정정 필요 여부

유형별 필수 공용컬럼은 서로 다르다.

- 은행거래: 계좌·거래구분 중심
- 카드거래: 카드·거래처 중심
- 개인경비: 직원·사업구분·업무유형 중심
- 상용급여: 직원·사업구분·업무유형 중심
- 일용급여: 근로자·사업구분·프로젝트·작업팀 중심

적용되지 않는 FK가 NULL이라는 이유만으로 미완료로 판정하지 않는다. `EvidenceTypePolicyService`는 EvidenceType별 필수 공용컬럼과 상태 판단정책을 결정한다. 필드 표시·화면 Metadata 책임까지 이 Service에 추가하지 않는다.

## 9. Transaction 책임

Transaction은 Evidence 원본값을 그대로 복제하는 계층이 아니다.

Transaction이 소유하는 항목:

- 실제 경제활동
- 지급·수납 대상
- Item
- Settlement
- 최종금액
- 거래일
- 거래상태
- 지급·수납 진행상태

Transaction 생성은 기본적으로 검증된 Evidence 공용컬럼을 사용한다. `raw_*`는 원본 대사와 보조판단에 사용할 수 있으나, 정규화된 공용값을 무시하고 회계분류 SSOT로 직접 사용하지 않는다.

## 10. Voucher 책임

Voucher가 소유하는 항목:

- 계정과목
- 차변·대변
- 분개금액
- 귀속기간
- 전기상태
- 마감상태
- 회계적 정정·취소

Evidence에는 계정과목·차변·대변을 저장하지 않는다.

추천분개는 Evidence 공용분류, Evidence 원본, Transaction, 거래처·직원·프로젝트·계좌·카드·팀 및 기존 학습·분개규칙을 종합할 수 있다. 최종 회계사실은 Voucher가 소유한다.

## 11. 연결과 역추적

공식 흐름:

```text
원 업무·기관자료
→ Evidence raw 원본보존
→ Evidence 공용컬럼 확인·정규화
→ Transaction
→ 추천분개
→ Voucher
```

Evidence–Transaction–Voucher 연결은 공용 Link 계약을 사용하며 다음 질문에 답할 수 있어야 한다.

- 이 Voucher는 어떤 Transaction에서 왔는가
- 이 Transaction은 어떤 Evidence에서 왔는가
- 이 Evidence는 어떤 원 업무·파일·승인자료에서 왔는가
- 최초 원본값과 현재 정규화값은 어떻게 다른가
- 누가 언제 분류·수정·삭제·정정했는가

## 12. 내부 승인형 추가 추적

다음은 모든 Evidence에 강제하는 Core가 아니라 내부 승인형의 조건부 추적영역이다.

- source_document_id
- source_header_id
- source_item_id
- source_group_id
- approval_request_id
- source_revision_id
- source_hash
- business_key_hash
- snapshot_json
- approved_at
- approved_by

계산 Revision이 없는 업무에 가짜 Revision을 만들지 않는다. Hash와 Snapshot도 승인·계산·멱등성 재현에 필요한 업무에만 적용한다.

## 13. 유형별 Grain

각 EvidenceType은 자체 Grain을 반드시 문서화한다.

| 유형 | Grain |
|---|---|
| 은행 | 은행 원 거래 1건 |
| 카드 | 카드 승인 또는 카드사 명세 Line 1건 |
| 세금계산서 | 세금계산서 원본 Header 1건 |
| 세금계산서 Raw Line | Evidence × 원 품목 Line |
| 개인경비 | 승인 신청 Item 1건 |
| 상용급여 | 승인문서 × 직원 Item |
| 일용급여 | 승인문서 × Group × Worker Item |
| 일용 Raw Line | Evidence × Calculation Line |

Header와 Raw Line은 이 Grain에 맞춰 합계·공통 원본과 반복 상세의 책임을 분리한다. Snapshot만으로 검색·대사에 필요한 핵심 물리 원본컬럼을 대체하지 않는다.

## 14. 금지 규칙

- 공용값으로 `raw_*` 덮어쓰기
- `raw_*` 값으로 공용분류를 검증 없이 자동확정
- 원본에 없던 값을 `raw_*`로 생성
- 공용컬럼을 EvidenceType별로 임의 누락
- 동일 의미 공용컬럼을 유형별로 다른 이름으로 생성
- Transaction Item·Settlement·Final을 Evidence 책임으로 이동
- 계정과목·차변·대변 또는 Voucher 상태를 Evidence에 저장
- 원본과 정규화값을 하나의 컬럼으로 혼용
- Snapshot만 저장하고 검색 가능한 핵심 `raw_*`를 생략
- 실제 원본 Grain을 무시하고 합계만 저장
- 코드 편의를 이유로 EvidenceType마다 독립 규칙 생성

## 15. 변경 절차

Evidence 관련 변경은 다음 순서로 수행한다.

1. 이 SSOT 확인
2. EvidenceType과 Grain 확정
3. 원본·공용분류·감사·승인추적·회계책임 분류
4. 기존 소비자와 운영자료 Dry-run
5. Forward Migration 설계
6. 신규컬럼 추가
7. 결정 가능한 값만 backfill
8. dual-write와 신규 우선 read-fallback
9. API·TableSettings·검색·정렬·Export 전환
10. Legacy 소비자 0건 확인
11. 별도 승인 후 구 컬럼 제거

추정 backfill은 금지하며 결정할 수 없는 행은 `MANUAL_REVIEW_REQUIRED`로 분류한다.

## 16. 관련 문서

- [CommonDictionary](CommonDictionary.md)
- [TableDictionary](TableDictionary.md)
- [ServiceDictionary](ServiceDictionary.md)
- [DecisionLog](DecisionLog.md)
- [ERP Architecture](ERPArchitecture.md)

프로젝트별 구현계획은 이 계약을 적용하는 산출물이며 SSOT가 아니다.
