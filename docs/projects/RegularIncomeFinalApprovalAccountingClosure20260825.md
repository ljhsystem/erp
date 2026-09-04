# 상용근로소득 최종승인 회계자료 생성 Closure

## 기준선

- 문서: 2013-08 상용근로소득, 직원 2명
- 지급총액 2,177,780원, 근로자 공제 157,380원, 실지급액 2,020,400원
- 인식일 2013-08-31, 지급일 2013-09-11
- Migration 05 역할형 Registry와 Migration 06 급여 공통 Context 적용 완료

## 구현 계약

- `RegularEmploymentIncomeService`는 결재·문서 상태와 바깥 Transaction만 소유한다.
- `RegularEmploymentIncomeAccountingGenerationService`는 최종승인 시 Evidence·직원 거래·기관 발생채무 거래·원본추적 Link Closure만 소유한다. 지급예정·납부·전표·분개는 소유하지 않는다.
- 중간 승인에서는 회계자료를 생성하지 않고 FINAL_APPROVAL에서만 Preflight와 생성을 수행한다.
- Evidence는 Header당 1건, 직원 급여 거래는 직원별 1건, 지급항목은 Line별 Item, 근로자 공제는 0원이 아닌 Line별 Settlement로 생성한다.
- 사용자부담은 납부기관·귀속월·인식일·방향·통화 정책별 Transaction으로 만들고 직원·보험별 Item을 유지한다.
- Evidence Link, 역할형 Accounting Registry와 Schedule Link를 모두 생성한다.

## 운영 Preflight 결과

- 결과: `EMPLOYER_CONTRIBUTION_INCOMPLETE`
- 누락: 고용보험 사용자부담, 고용안정·직업능력개발 부담, 산재보험 사용자부담
- Preflight 전후: Evidence 0, Accounting Registry 0, 지급예정 0, Schedule Link 0으로 불변
- 판정: 코드 전환 완료 / 운영 승인 미실행 / 사용자부담 법정 계산결과 완결 후 재검증 필요
