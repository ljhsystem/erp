# 기초금액 구현 Closure (2026-09-04)

## 목적

회계관리 > 기초정보관리 > 기초금액을 Placeholder에서 실제 회계 원장 진입 기능으로 전환한다.

## 확정 계약

- Grain: 회사 × 회계연도 1건
- 기준일: 선택 회계연도의 전년도 12월 31일
- 금액 원본: `ledger_voucher_lines`의 차변·대변
- 상태: DRAFT → REVIEW_REQUESTED → REVIEWED → POSTED
- 장부 반영: 기존 전표 전기 경로 재사용
- 증빙: 생성하거나 연결하지 않음
- 중복 경로 `/ledger/opening-balances`: 제거

## 구조

`OpeningBalanceController → OpeningBalanceService → OpeningBalanceModel`을 사용한다. Service는 `VoucherService`를 조합하고 외부 Transaction 참여 계약으로 관계행과 전표가 함께 Commit 또는 Rollback되도록 한다.

## 보호 범위

- 기존 전표·분개·증빙 데이터 DML 없음
- Trigger 생성·변경 없음
- 캘린더·일정 변경 없음
- 기존 계정과목 및 TableSettings 초기화 없음

## 개인 권한 보존 보정

- `개인(REPLACE)` 방식 사용자가 기존 기초금액 WEB 권한을 보유한 경우 신규 기초금액 API 권한을 함께 보존한다.
- 역할권한만 추가되어 개인 권한 사용자의 API가 403으로 차단되는 초기화 결함을 `20260904_06`으로 보정한다.
- 선택자료 API 오류가 발생해도 목록 테이블과 신규등록 버튼의 화면 골격은 먼저 초기화한다.
