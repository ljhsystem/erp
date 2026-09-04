# 2013-08 상용근로소득 보험별 계산기초·가족수 Closure

## 확정 계약

- 국민연금: 확정 기준소득월액 이력이 없으면 비과세 근로소득을 제외한 지급항목으로 자동 제안하고, 기준소득월액 확정 단계에서 1,000원 미만을 버린다.
- 건강보험: 확정 보수월액 이력이 없으면 비과세 근로소득을 제외한 보수로 자동 제안한다. 법령상 비과세 포함 예외는 카드의 보수월액 Snapshot으로 보정한다.
- 고용보험: 기존 `INSURABLE_REMUNERATION` Closure를 유지한다.
- 근로소득세 가족수: `dependent_count_snapshot`을 근로소득세 카드에서 직접 입력하고 카드의 재계산 버튼으로 계산한다.

## 2013-08 결과

| 구분 | 계산기초 | 자동계산 |
|---|---:|---:|
| 국민연금 | 988,000원 | 44,460원 |
| 건강보험 | 988,890원 | 29,120원 |
| 장기요양보험 | 건강보험료 29,120원 | 1,900원 |
| 고용보험 | 988,890원 | 6,420원 |
| 근로소득세 | 과세대상 988,890원·가족수 1명 | 0원 |
| 지방소득세 | 근로소득세 0원 | 0원 |

- 이정호는 고용보험 자동계산 6,420원, 실제 적용 0원, 조정 -6,420원을 유지한다.
- 박한호는 모든 자동계산액과 실제 적용액이 일치한다.
- 국민연금 -4,540원, 건강보험 -2,940원, 장기요양 -190원 조정은 계산기초 오류가 해소되어 0원으로 정리한다.

## 실행

- Migration: `php tools/apply_regular_income_basis_policy_closure.php up`
- 정책 검증: `php tools/apply_regular_income_basis_policy_closure.php verify`
- Runtime 회귀: `php tests/regression/regular_income_basis_dependent_closure.php`
- 실제 자료 저장: `php tools/save_regular_income_201308_actual.php`
