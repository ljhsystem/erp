CREATE TABLE IF NOT EXISTS `ledger_vouchers` (
  `id` char(36) NOT NULL COMMENT '전표 식별자. 일반전표 1건을 구분하는 UUID',
  `code` varchar(50) NOT NULL COMMENT '전표 관리 코드. 화면과 검색에서 사용하는 전표 고유 코드',
  `voucher_date` date NOT NULL COMMENT '전표일자. 회계상 전표가 귀속되는 기준 일자',
  `ref_type` varchar(30) NOT NULL COMMENT '원참조 유형. CLIENT, PROJECT, ACCOUNT, CARD, EMPLOYEE, ORDER 중 하나',
  `ref_id` varchar(100) NOT NULL COMMENT '원참조 ID. ref_type이 가리키는 업무 데이터의 식별값',
  `status` varchar(20) NOT NULL DEFAULT 'draft' COMMENT '전표 상태. draft=작성중, posted=확정, locked=마감잠금, deleted=삭제상태',
  `summary_text` varchar(255) DEFAULT NULL COMMENT '전표 헤더 적요. 전표 전체를 설명하는 요약 문구',
  `note` varchar(500) DEFAULT NULL COMMENT '전표 비고. 업무 담당자가 추가로 기록하는 보조 설명',
  `memo` text DEFAULT NULL COMMENT '전표 메모. 상세 업무 메모 또는 내부 기록',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '전표 최초 생성 일시',
  `created_by` varchar(100) DEFAULT NULL COMMENT '전표 최초 생성 주체. ActorHelper 기준 actor 문자열',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '전표 최종 수정 일시',
  `updated_by` varchar(100) DEFAULT NULL COMMENT '전표 최종 수정 주체. ActorHelper 기준 actor 문자열',
  `deleted_at` datetime DEFAULT NULL COMMENT '전표 소프트 삭제 일시. NULL이면 활성 데이터',
  `deleted_by` varchar(100) DEFAULT NULL COMMENT '전표 소프트 삭제 주체. ActorHelper 기준 actor 문자열',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ledger_vouchers_code` (`code`),
  KEY `idx_ledger_vouchers_date` (`voucher_date`),
  KEY `idx_ledger_vouchers_ref` (`ref_type`, `ref_id`),
  KEY `idx_ledger_vouchers_status` (`status`),
  KEY `idx_ledger_vouchers_deleted_at` (`deleted_at`)
) COMMENT='일반전표 헤더 테이블. 전표일자, 참조유형, 상태, 적요 등 전표 상단 정보를 저장한다';

CREATE TABLE IF NOT EXISTS `ledger_voucher_lines` (
  `id` char(36) NOT NULL COMMENT '전표 라인 식별자. 전표 1건에 속한 개별 분개 라인의 UUID',
  `voucher_id` char(36) NOT NULL COMMENT '상위 전표 ID. ledger_vouchers.id를 참조하는 전표 헤더 식별자',
  `line_no` int NOT NULL COMMENT '라인 순번. 전표 내 표시 순서를 나타내는 번호',
  `account_code` varchar(50) NOT NULL COMMENT '계정코드. ledger_accounts.account_code를 참조하는 분개 대상 계정',
  `debit` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT '차변 금액. 차변 라인에서만 입력되는 금액',
  `credit` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT '대변 금액. 대변 라인에서만 입력되는 금액',
  `line_summary` varchar(255) DEFAULT NULL COMMENT '라인 적요. 개별 분개 라인의 설명',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '라인 최초 생성 일시',
  `created_by` varchar(100) DEFAULT NULL COMMENT '라인 최초 생성 주체. ActorHelper 기준 actor 문자열',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '라인 최종 수정 일시',
  `updated_by` varchar(100) DEFAULT NULL COMMENT '라인 최종 수정 주체. ActorHelper 기준 actor 문자열',
  `deleted_at` datetime DEFAULT NULL COMMENT '라인 소프트 삭제 일시. NULL이면 활성 라인',
  `deleted_by` varchar(100) DEFAULT NULL COMMENT '라인 소프트 삭제 주체. ActorHelper 기준 actor 문자열',
  PRIMARY KEY (`id`),
  KEY `idx_ledger_voucher_lines_voucher_id` (`voucher_id`),
  KEY `idx_ledger_voucher_lines_account_code` (`account_code`),
  KEY `idx_ledger_voucher_lines_deleted_at` (`deleted_at`)
) COMMENT='일반전표 분개 라인 테이블. 차변/대변 금액과 계정코드를 저장하는 실질 분개 데이터';

CREATE TABLE IF NOT EXISTS `ledger_voucher_payments` (
  `id` char(36) NOT NULL COMMENT '전표 결제수단 식별자. 전표에 연결된 결제 정보 1건의 UUID',
  `voucher_id` char(36) NOT NULL COMMENT '상위 전표 ID. ledger_vouchers.id를 참조하는 전표 헤더 식별자',
  `payment_type` varchar(30) NOT NULL COMMENT '결제수단 유형. 예: CASH, BANK, CARD, OFFSET',
  `payment_id` varchar(100) NOT NULL COMMENT '결제수단 식별값. 계좌, 카드, 기타 결제 객체의 ID 또는 코드',
  `amount` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT '해당 결제수단으로 연결된 금액',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '결제수단 정보 생성 일시',
  `created_by` varchar(100) DEFAULT NULL COMMENT '결제수단 정보 생성 주체. ActorHelper 기준 actor 문자열',
  PRIMARY KEY (`id`),
  KEY `idx_ledger_voucher_payments_voucher_id` (`voucher_id`),
  KEY `idx_ledger_voucher_payments_payment` (`payment_type`, `payment_id`)
) COMMENT='전표 결제수단 연결 테이블. 전표와 결제 객체를 분리하여 결제수단 정보를 저장한다';
