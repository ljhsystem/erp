CREATE TABLE `system_notification_events` (
  `id` varchar(36) NOT NULL COMMENT '알림 이벤트 식별자',
  `source_domain_code` varchar(50) NOT NULL COMMENT '업무 원천 도메인 코드',
  `source_id` varchar(100) DEFAULT NULL COMMENT '업무 원천 식별자',
  `event_type_code` varchar(80) NOT NULL COMMENT '알림 이벤트 유형 코드',
  `event_key` varchar(191) NOT NULL COMMENT '업무 사실 멱등키',
  `title` varchar(255) NOT NULL COMMENT '알림 제목 Snapshot',
  `message` text NOT NULL COMMENT '알림 내용 Snapshot',
  `template_key` varchar(100) DEFAULT NULL COMMENT '공용 템플릿 키',
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '최소 업무 Payload JSON',
  `importance_code` varchar(20) NOT NULL DEFAULT 'NORMAL' COMMENT '중요도 코드',
  `occurred_at` datetime NOT NULL COMMENT '업무 사실 발생일시',
  `request_key` varchar(191) DEFAULT NULL COMMENT '요청 추적키',
  `created_by` varchar(100) NOT NULL COMMENT '생성 Actor',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '생성일시',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_notification_event_key` (`event_key`),
  KEY `idx_notification_event_source` (`source_domain_code`,`source_id`,`event_type_code`),
  KEY `idx_notification_event_occurred` (`occurred_at`),
  KEY `idx_notification_event_request` (`request_key`),
  CONSTRAINT `chk_notification_event_payload_json` CHECK (`payload_json` IS NULL OR json_valid(`payload_json`)),
  CONSTRAINT `chk_notification_event_importance` CHECK (`importance_code` IN ('LOW','NORMAL','HIGH','CRITICAL'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='공용 알림 업무 이벤트 SSOT';

CREATE TABLE `system_notification_recipients` (
  `id` varchar(36) NOT NULL COMMENT '알림 수신자 식별자',
  `event_id` varchar(36) NOT NULL COMMENT '알림 이벤트 식별자',
  `recipient_user_id` varchar(36) NOT NULL COMMENT '수신 사용자 식별자',
  `delivery_policy_code` varchar(20) NOT NULL DEFAULT 'OPTIONAL' COMMENT '필수 또는 선택 전달 정책',
  `action_page_key` varchar(191) DEFAULT NULL COMMENT '업무 이동 Page Registry 키',
  `action_entity_type_code` varchar(80) DEFAULT NULL COMMENT '업무 대상 유형 코드',
  `action_entity_id` varchar(100) DEFAULT NULL COMMENT '업무 대상 식별자',
  `action_params_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '업무 이동 파라미터 JSON',
  `action_url_fallback` varchar(1000) DEFAULT NULL COMMENT '검증된 내부 상대경로',
  `is_read` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'IN_APP 읽음 여부',
  `read_at` datetime DEFAULT NULL COMMENT 'IN_APP 읽은 일시',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '생성일시',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_notification_recipient` (`event_id`,`recipient_user_id`),
  KEY `idx_notification_recipient_feed` (`recipient_user_id`,`is_read`,`created_at`),
  CONSTRAINT `fk_notification_recipient_event_core` FOREIGN KEY (`event_id`) REFERENCES `system_notification_events` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_notification_recipient_user_core` FOREIGN KEY (`recipient_user_id`) REFERENCES `auth_users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_notification_recipient_policy` CHECK (`delivery_policy_code` IN ('MANDATORY','OPTIONAL')),
  CONSTRAINT `chk_notification_action_params_json` CHECK (`action_params_json` IS NULL OR json_valid(`action_params_json`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='공용 알림 수신자 및 IN_APP 읽음 SSOT';

CREATE TABLE `system_notification_deliveries` (
  `id` varchar(36) NOT NULL COMMENT '알림 전달 식별자',
  `recipient_id` varchar(36) NOT NULL COMMENT '알림 수신자 식별자',
  `channel_code` varchar(30) NOT NULL COMMENT '전달 채널 코드',
  `provider_code` varchar(50) DEFAULT NULL COMMENT '외부 Provider 코드',
  `delivery_status_code` varchar(30) NOT NULL DEFAULT 'QUEUED' COMMENT '전달 상태 코드',
  `queued_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '전달 대기일시',
  `processing_at` datetime DEFAULT NULL COMMENT '전달 처리 시작일시',
  `sent_at` datetime DEFAULT NULL COMMENT '전달 성공일시',
  `failed_at` datetime DEFAULT NULL COMMENT '전달 실패일시',
  `retry_count` int unsigned NOT NULL DEFAULT 0 COMMENT '재시도 횟수',
  `next_attempt_at` datetime DEFAULT NULL COMMENT '다음 재시도 예정일시',
  `provider_message_id` varchar(191) DEFAULT NULL COMMENT 'Provider 메시지 식별자',
  `failure_code` varchar(100) DEFAULT NULL COMMENT '실패 코드',
  `failure_message` varchar(500) DEFAULT NULL COMMENT '정제된 실패 내용',
  `request_key` varchar(191) DEFAULT NULL COMMENT '전달 요청 추적키',
  `locked_at` datetime DEFAULT NULL COMMENT 'Worker 선점일시',
  `locked_by` varchar(100) DEFAULT NULL COMMENT 'Worker 식별자',
  `updated_by` varchar(100) DEFAULT NULL COMMENT '수정 Actor',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '생성일시',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_notification_delivery_channel` (`recipient_id`,`channel_code`),
  KEY `idx_notification_delivery_queue` (`delivery_status_code`,`next_attempt_at`,`locked_at`),
  KEY `idx_notification_delivery_provider_message` (`provider_code`,`provider_message_id`),
  CONSTRAINT `fk_notification_delivery_recipient_core` FOREIGN KEY (`recipient_id`) REFERENCES `system_notification_recipients` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_notification_delivery_status` CHECK (`delivery_status_code` IN ('QUEUED','PROCESSING','SENT','FAILED','PERMANENT_FAILED','CANCELLED','SKIPPED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='공용 알림 채널별 전달 SSOT';

CREATE TABLE `system_notification_channel_policies` (
  `event_type_code` varchar(80) NOT NULL COMMENT '알림 이벤트 유형 코드',
  `channel_code` varchar(30) NOT NULL COMMENT '전달 채널 코드',
  `delivery_policy_code` varchar(20) NOT NULL DEFAULT 'OPTIONAL' COMMENT '필수 또는 선택 정책',
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '정책 사용 여부',
  `max_attempts` smallint unsigned NOT NULL DEFAULT 1 COMMENT '최대 전달 시도 횟수',
  `retry_interval_seconds` int unsigned NOT NULL DEFAULT 300 COMMENT '재시도 간격 초',
  `retention_days` int unsigned DEFAULT NULL COMMENT '보존 권장 일수',
  `created_by` varchar(100) NOT NULL COMMENT '생성 Actor',
  `updated_by` varchar(100) DEFAULT NULL COMMENT '수정 Actor',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '생성일시',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  PRIMARY KEY (`event_type_code`,`channel_code`),
  CONSTRAINT `chk_notification_channel_policy` CHECK (`delivery_policy_code` IN ('MANDATORY','OPTIONAL'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='알림 이벤트별 채널 정책 SSOT';

CREATE TABLE `system_notification_user_preferences` (
  `user_id` varchar(36) NOT NULL COMMENT '사용자 식별자',
  `channel_code` varchar(30) NOT NULL COMMENT '전달 채널 코드',
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '사용자 채널 사용 여부',
  `updated_by` varchar(100) DEFAULT NULL COMMENT '수정 Actor',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '생성일시',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  PRIMARY KEY (`user_id`,`channel_code`),
  CONSTRAINT `fk_notification_preference_user` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='사용자별 선택 알림 채널 설정 SSOT';

INSERT INTO `system_notification_channel_policies`
(`event_type_code`,`channel_code`,`delivery_policy_code`,`is_enabled`,`max_attempts`,`retry_interval_seconds`,`retention_days`,`created_by`)
VALUES
('APPROVAL_SUBMITTED','IN_APP','MANDATORY',1,1,300,1825,'SYSTEM:MIGRATION'),
('APPROVAL_APPROVED','IN_APP','MANDATORY',1,1,300,1825,'SYSTEM:MIGRATION'),
('APPROVAL_REJECTED','IN_APP','MANDATORY',1,1,300,1825,'SYSTEM:MIGRATION'),
('TRAINING_ASSIGNED','IN_APP','MANDATORY',1,1,300,1825,'SYSTEM:MIGRATION'),
('TRAINING_UPDATED','IN_APP','MANDATORY',1,1,300,1825,'SYSTEM:MIGRATION'),
('TRAINING_CANCELLED','IN_APP','MANDATORY',1,1,300,1825,'SYSTEM:MIGRATION')
ON DUPLICATE KEY UPDATE `event_type_code`=VALUES(`event_type_code`);
