# 상용근로소득 증감 지급항목 SSOT Closure

## 목적과 범위

- 상용근로소득 증감행의 항목명·과세 여부 자유입력을 제거한다.
- `institution_employment_contracts_pay_components`를 근로계약과 공동 지급항목 SSOT로 사용한다.
- `OTHER_PAY`는 UI 가상값이 아니라 위 마스터의 정식 과세 항목이며 일반 항목과 달리 서로 다른 사유의 복수 증액을 허용한다. 기타 감액은 공제 Line이 아니라 과세 PAY 감액이며 과세 지급 잔액을 초과할 수 없다.
- 적용일 유효성, 활성상태, 명칭, 과세정책은 서버에서 재검증한다.
- 공제 추가징수·환급 구조는 별도 책임으로 유지한다.

## 저장 계약

- `source_reference_id`: 급여항목 마스터 PK
- `source_key`: `PAY_COMPONENT|지급항목코드|INCREASE 또는 DECREASE|멱등토큰`
- `item_code`: 증감행별 고유 조정 코드
- `item_name_snapshot`: 계산 당시 지급항목명
- `taxable_flag`: 계산 당시 마스터 과세정책의 계산 적용값
- `business_reason`, `processed_at`, `processed_by`: 증감 사유와 처리 감사정보

## 기존 데이터 조사

- 2026-08-24 실제 DB에서 `INCREASE/DECREASE` PAY Line은 0건이었다.
- 자동 매핑 또는 Backfill 대상이 없으며 Migration을 만들지 않는다.
- 기존 확정 계약기준·과거급여 Snapshot은 변경하지 않는다.

## 검증 기준

- 과세정책·명칭 변조 차단
- 비활성·기간외·존재하지 않는 마스터 차단
- 동일 지급항목 복수 증감행 식별
- 증액 가산, 동일 지급항목 감액 및 음수 방지
- 과세·비과세·소득세·사회보험 계산기초 재계산
- 결재 재계산과 급여 거래 Projection 합계 일치
- 근로계약 옵션 회귀

## 문서 상태

- 상태: 완료 후 기록용 보관
- 운영 기준: ServiceDictionary, RouteDictionary, TableDictionary, DecisionLog로 승격
