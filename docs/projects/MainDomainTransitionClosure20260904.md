# Main Domain Transition Closure 2026-09-04

## 판정

- Code 전환: 진행 중
- DB Registry 전환: Migration 작성·Rollback 검증·운영 적용 완료
- Calendar: 도메인 명칭 참조만 검사하고 내부 기능 리팩토링은 유예

## 완료한 작업

- 기본 진입 Redirect를 `/main`으로 전환
- Main 홈 View·CSS 소유 명칭을 `main-home`으로 전환
- Main 설정 화면의 TableSettings Key를 `dashboard.settings.*`에서 `main.settings.*`로 전환
- `main_calendar_*` 5개 물리 테이블과 `dashboard_*` 0개를 운영 DB에서 확인
- Page Registry·Menu Registry·Permission Page Key·UserSetting·URL 이관 Migration 작성
- Migration 실제 SQL을 운영 DB Transaction에서 시험한 후 전체 Rollback 확인

## 운영 적용 전·후 검증

| 항목 | 적용 전 | 시험 적용 후 |
| --- | ---: | ---: |
| `dashboard_*` Table | 0 | 0 |
| `main_*` Table | 5 | 5 |
| `dashboard.*` Page Registry | 7 | 0 |
| Main Page Registry | 0 | 7 |
| `dashboard.*` Menu Registry | 7 | 0 |
| Permission Legacy Page Key | 33 | 0 |
| UserSetting Legacy Page Key | 2 | 0 |
| `/dashboard*` Registry URL | 60 | 0 |

## 완료 경계

- `20260904_02_complete_main_domain_registry.up.sql` 운영 적용 완료
- `PageKeyResolver`의 Main canonical Page Key 전환 완료
- `/dashboard*` Redirect 호환층 제거 완료
- 업무 도메인의 Dashboard 화면 역할명인 `ledger.dashboard`, `site.dashboard`, `institution.dashboard` 등은 정상 범위로 유지
