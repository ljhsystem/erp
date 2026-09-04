# 개인경비 공식 분류 Coverage Closure

## 공식 Mapping

| 비용분류 | 기본 차변 계정 | 근거 |
|---|---|---|
| MEAL | 551030 판관-복리후생비 | 기존 공식 Rule |
| TRANSPORTATION | 551040 판관-여비교통비 | 기존 공식 Rule |
| FUEL | 551130 판관-차량유지비(유류비포함) | 차량 운행비 계정 |
| PARKING | 551130 판관-차량유지비(유류비포함) | 차량 운행 부대비용 |
| TOLL | 551130 판관-차량유지비(유류비포함) | 차량 운행 부대비용 |
| ACCOMMODATION | 551040 판관-여비교통비 | 출장 숙박비 |
| TAXES_AND_DUES | 551091 판관-세금과공과(일반) | 기존 공식 Rule |
| FEES_AND_COMMISSIONS | 551200 판관-지급수수료 | 기존 공식 Rule |
| SUPPLIES | 551220 판관-소모품비 | 기존 공식 Rule |
| COMMUNICATION | 551230 판관-통신비 | 계정 Master 직접 대응 |
| ENTERTAINMENT | 551060 판관-접대비 | 계정 Master 직접 대응 |
| OFFICE_SUPPLIES | 551360 판관-사무용품비 | 계정 Master 직접 대응 |
| FREIGHT | 551240 판관-운반비 | 계정 Master 직접 대응 |
| EQUIPMENT_RENTAL | 551050 판관-임차료 | 장비 임차·사용료 |
| OTHER | 551380 판관-기타경비 | 활성·전기 가능 판관비 기타경비 계정이 정확히 1건 |

모든 계정은 운영 Master에서 활성·전기 가능 상태다. 신규 계정은 생성하지 않는다. 공통 대변은 기존 `EMPLOYEE_ACCRUED_EXPENSE / 216100 미지급비용` Rule을 사용한다.

## 기본 Rule과 학습 Rule

기본 분류 Rule은 Coverage 안전망으로 유지한다. 임시저장에서는 사용자의 최종 입력 Snapshot만 기록하고, 검토완료·전기 시점에만 유효 Learning Event를 생성한다. 반복된 계정 수정은 거래처·적요분류 등 구체 조건 Candidate로 집계하며 기본 Rule을 직접 변경하지 않는다. Candidate 승인 또는 정책상 저위험 자동승격 뒤에만 `ledger_journal_rules` CREATE Revision으로 등록한다. 이후 변경은 UPDATE Revision으로 남기고 취소·역분개는 학습효과를 상쇄한다. 실제 사용 또는 Revision이 있는 Rule은 물리삭제하지 않는다.

## 운영 계약

- 신규 Seed: `20260825_03_seed_personal_expense_category_coverage_rules`
- 추가 Rule·CREATE Revision: 각각 10건
- 적용일: `2013-07-19`
- OTHER: `item_code=OTHER`에만 매칭
- 레거시 3개 계정 컬럼: NULL
- Down: 기준데이터 삭제를 막는 forward-only 차단
