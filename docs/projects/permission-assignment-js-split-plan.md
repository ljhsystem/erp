# 권한부여 JS 모듈 분리 계획

## 대상

- `public/assets/js/pages/main/settings/organization/permission-assignment.js`
- 엔트리 파일은 테이블 설정 정책을 전용 ES 모듈로 분리하여 1,500라인 미만으로 정리했다.

## 원칙

- 현재 화면 동작과 API 계약을 변경하지 않는다.
- 원본 전체 복사 후 책임별로 불필요한 함수를 제거하는 방식으로 정리한다.
- 문자열 실행, `eval`, `Function`, `String.raw` 기반 분리를 사용하지 않는다.
- 각 단계마다 `node --check`와 역할 선택·권한 조회·저장·순서변경 회귀검증을 수행한다.

## 분리 순서

1. 완료: 공용 Table Setting 상태 정규화 정책을 `permission-assignment/table-settings-policy.js`로 분리했다.
2. 완료: Permission Master와 역할 Mapping Set 합성을 `permission-assignment/permission-cache.js`로 분리했다.
3. 완료: HTML 이스케이프, 알림, 상태 배지, 공용 응답 행 추출을 `permission-assignment/ui-helpers.js`로 분리했다.
4. 후속 후보: 역할목록 DataTable과 역할 선택 흐름을 `role-list.js`로 분리한다.
5. 후속 후보: 권한 트리 상태와 체크박스 동기화를 `permission-tree.js`로 분리한다.
6. 후속 후보: 권한 DataTable 렌더링과 검색·표시 정책을 `permission-table.js`로 분리한다.
7. 후속 후보: 순서변경 계산과 저장 흐름을 `permission-reorder.js`로 분리한다.
8. 후속 후보: 일괄 권한 저장과 API 호출을 `permission-save.js`로 분리한다.

## 완료 기준

- 역할 선택 후 권한 상태가 기존과 동일하게 표시된다.
- 전체선택·페이지선택·개별선택 상태가 동일하게 동작한다.
- 권한 저장은 단일 일괄 요청을 유지한다.
- 권한 순서변경과 테이블 설정에 회귀가 없다.
- 모든 JS 파일이 1,500라인 미만이며 실제 ES 모듈로 구성된다.
