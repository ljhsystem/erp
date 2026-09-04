# Auth Permission Architecture

## SSOT

- Permission Master: `auth_permissions`
- Role Master: `auth_roles`
- Role Permission: `auth_role_permissions`
- User Permission Mode: `auth_user_permission_profiles`
- User Permission Mapping: `auth_user_permissions`
- User Permission Audit: `auth_user_permission_audits`
- Runtime Effective Resolver: `PermissionService`

## 판정 순서

사용자 존재·승인·활성, 역할 존재·활성, Permission 존재·활성을 검증한다. `ROLE`은 역할 Set, `EXTEND`는 역할 Set과 개인 Set의 합집합, `REPLACE`는 개인 Set만 사용하며 빈 개인 Set도 그대로 최종 0건이다. `super_admin`도 세 Mode를 사용할 수 있지만 핵심 관리권한과 마지막 복구 관리자 Guard를 항상 통과해야 한다. 직원 퇴사 상태는 인증 Permission 판정에 직접 결합하지 않는다.

## 저장과 보호

개인권한은 사용자 한 명의 Mode와 전체 Permission Set으로 저장한다. 조회 상태의 canonical hash를 `state_version`으로 사용하며 Profile, Mapping 차집합과 `MODE`/`GRANT`/`REVOKE` Audit을 같은 트랜잭션에 저장한다. `super_admin`은 자기 자신을 포함해 변경할 수 있으나 REPLACE에서도 핵심 관리권한을 유지해야 한다. `admin`은 자기 자신·다른 admin·super_admin을 변경할 수 없고 일반 사용자만 변경할 수 있으며, 일반 역할은 권한관리를 할 수 없다. Actor Effective 범위 밖 부여와 마지막 복구 관리자 상실은 차단한다.

## UI

권한부여는 `[개별] [역할별]` 순서다. 개인 사용자·개인 Permission·역할목록·역할 Permission은 서로 다른 Table/View Setting Key를 사용한다. 서버가 `permission_mode`, `role_allowed`, `user_allowed`, `effective_allowed`, `editable`, `readonly_reason`을 반환한다.

개별과 역할별 권한목록은 `RolePermissionService::getPermissionTreeForRole()`의 동일 Permission Master Tree를 사용한다. 페이지 계층, WEB/API 보정, Page Registry 메타, 정렬과 자동 갱신은 역할별 목록을 기준으로 하며 저장되는 선택 Set만 역할별 `auth_role_permissions`, 개인별 `auth_user_permissions`로 분리한다.

일반 HTTP 요청에서는 현재 WEB/API Route가 `PermissionRegistry`에 등록한 Permission Key만 신규 부여 목록에 노출한다. DB에 남은 과거 Permission과 기존 역할·개인 Mapping은 현재 Route가 없으면 자동동기화에서 물리삭제한다. 권한변경 영구 감사이력은 Permission ID 대신 Key·Name Snapshot으로 보존한다. Route를 로드하지 않는 독립 CLI 검사에서는 기존 전체 Master 조회 호환성을 유지한다.

Permission 자동동기화는 현재 Route Set 전체를 SSOT로 사용한다. 신규 Route는 같은 Permission Key를 생성하고, 메타 변경은 기존 ID를 유지한 채 갱신하며, 삭제된 Route의 Permission은 역할·개인 Mapping과 Permission 원본을 한 트랜잭션에서 물리삭제한다. 영구 감사이력의 Permission FK는 DB `ON DELETE SET NULL`을 따르고 Key·Name Snapshot을 유지한다. 같은 Key가 다시 등록되면 신규 Permission으로 생성되며 과거 Mapping은 복원하지 않는다. 등록 오류 또는 중복 Key 메타 충돌이 하나라도 발생하면 잘못된 대량 삭제를 막기 위해 해당 실행의 stale 삭제 단계를 건너뛴다.

운영 Permission 물리삭제는 별도 승인 전에 실행되지 않도록 `ERP_PERMISSION_ROUTE_HARD_DELETE_ENABLED=1` 환경 플래그로 잠긴다. 플래그가 없으면 생성·메타 갱신만 수행하고 stale 물리삭제는 로그와 함께 건너뛴다.

두 권한목록의 공용 테이블 설정 물리 메타는 Permission Master인 `auth_permissions` 14개 컬럼으로 동일하다. 역할·개인 매핑 테이블은 체크 상태의 저장 SSOT이므로 보기 컬럼 메타에 합치지 않는다. 개별과 역할별은 서로 다른 사용자 설정 키를 사용하므로 표시·순서·사용컬럼명·필수구분·열너비는 화면별로 독립 저장한다.

기존 역할별 권한부여 화면·조회·저장 권한을 모두 가진 `admin`은 개별권한 list/detail/save API도 사용할 수 있다. 단, `admin` 사용자의 개인권한 변경은 대상 보호 정책에 따라 `super_admin`만 가능하다.
