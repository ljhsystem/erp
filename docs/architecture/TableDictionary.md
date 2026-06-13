# Table Dictionary

## System

| Table | Domain | Purpose | Relations |
| --- | --- | --- | --- |
| system_page_registry | PageRegistry | Stores canonical ERP page rows keyed by `page_key`, including module/menu/page labels, breadcrumb, and representative WEB route information promoted from the current `PAGE_MAPPING`. | Prepared for future permission, breadcrumb, sitemap, and menu consumers through `page_key`, `default_route_key`, and `default_route_url` |
| system_menu_registry | MenuRegistry | Stores ERP menu display-policy rows keyed by `menu_key`, with nullable `page_key` links to `system_page_registry`, per-surface visibility flags, ordering, icon, and representative entry URL. | Seeded from current Sidebar, Settings Menu, SiteMap, and Navbar structures; prepared for future menu consumers through `page_key`, `visible_in_sidebar`, `visible_in_settings`, `visible_in_sitemap`, and `visible_in_navbar` |

## Auth

| Table | Domain | Purpose | Relations |
| --- | --- | --- | --- |
| auth_permissions | Permission | Stores ERP permission master rows including `permission_key`, `permission_name`, `description`, `category`, `page_key`, and `is_active`. | Referenced by `auth_role_permissions.permission_id`, synchronized by `PermissionRegistry`, logically linked to `system_page_registry.page_key` |
| auth_role_permissions | RolePermission | Stores role-to-permission mappings keyed by `role_id + permission_id` and acts as the runtime permission source of truth. | Links `auth_roles.id` to `auth_permissions.id` |

현재 실제 사용 중인 Evidence 및 생성센터 핵심 테이블 사전이다.

| 테이블명 | 도메인 | 용도 | 주요 관계 |
| --- | --- | --- | --- |
| ledger_evidence_payloads | EvidencePayload | 증빙별 mapped_payload_json, raw_json, format_id, source_key, payload_hash를 보관하는 payload 저장소 | `(evidence_type, evidence_id)` 기준으로 processing, links, evidence body와 연결 |
| ledger_evidence_processing | EvidenceStatus | 증빙별 processing_status, review_status, last_error_message를 보관하는 상태 저장소 | `(evidence_type, evidence_id)` 기준 payload와 1:1 연결, logs의 부모 |
| ledger_evidence_links | EvidenceLink | 증빙과 거래/전표/processing item 등 target을 연결하는 링크 저장소 | `(evidence_type, evidence_id)`에서 `(target_type, target_id)`로 연결 |
| ledger_processing_items | EvidenceGeneration | 생성센터 split/merge, 보정 item, processing tree를 관리하는 작업 item 저장소 | evidence payload, processing item actions, voucher/transaction 생성 흐름과 연결 |
| ledger_processing_item_actions | EvidenceGeneration | processing item의 split/merge/보정 action 이력을 기록한다. | `processing_item_id`로 `ledger_processing_items.id`와 연결 |
| ledger_evidence_processing_logs | EvidenceStatus | 증빙 처리 상태 변경 및 처리 이벤트 로그를 보관한다. | `processing_id`로 `ledger_evidence_processing.id`와 연결 |
| ledger_evidence_bank | EvidenceBody | 은행 증빙 본문을 보관한다. | `id`가 evidence_id 역할을 하며 payload/status/link와 evidence_type 기준으로 연결 |
| ledger_bank_transactions | FundsLegacy | 계좌별 거래내역 및 은행 원천 거래를 보관하는 기존 자금관리 테이블이다. | 은행 증빙 생성, processing item backfill, 자금관리 화면과 연결 |
| ledger_transactions | Transaction | 거래입력의 거래 헤더/본문 기준 테이블이다. | evidence link의 `target_type='TRANSACTION'`, voucher와 연결 |
| ledger_vouchers | Voucher | 전표입력/전표검토의 전표 헤더 기준 테이블이다. | evidence link의 `target_type='VOUCHER'`, transaction과 연결 |

## 운영 규칙

- 신규 테이블을 런타임 코드에서 사용하기 전 이 사전에 등록한다.
- 삭제 예정 테이블은 삭제 전 "대체 테이블"과 "잔여 참조"를 기록한다.
- payload, status, link, body 역할을 한 테이블에 다시 합치지 않는다.
- DB 변경은 별도 승인 후 수행한다.
