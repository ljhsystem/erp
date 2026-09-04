# ERP 공용 메일 후속 P1

## ApprovalNotificationService 휴면 경로

- `ApprovalNotificationService::sendApprovalMail()`은 저장소에 존재하지 않는 `/public/api/smtp/mailer_api.php`에 의존한다.
- HTTP 상태와 응답 본문을 검증하지 않고 성공 로그를 기록하며 예외도 호출부에 전파하지 않는다.
- 현재 호출부는 없으며 이번 발신자 프로필 작업에서 활성화하거나 결재·시스템 인앱 알림을 이메일로 전환하지 않는다.
- 향후 활성화 전 공용 `MailService`와 `SUKHYANG_APP_ADMIN` 프로필로 재설계하고 실패 계약·개인정보 최소 로그·Mock을 별도 승인받아야 한다.

## 비밀번호 찾기 보안

- 현재 `AuthService::recoverPassword()`는 임시 비밀번호를 DB에 저장한 뒤 화면에 평문으로 표시하며 이메일을 발송하지 않는다.
- 이번 발신자 프로필 범위에서는 변경하지 않는다.
- 향후 일회용 만료 Token, 사용 후 폐기, Rate Limit, 감사로그와 `SUKHYANG_APP_ADMIN` 보안메일 흐름을 별도 설계·승인한다.

## 운영 Secret

- 과거 Git 추적 설정과 작업 출력에 노출된 Google 앱 비밀번호는 폐기 대상으로 유지한다.
- 신규 Secret은 `SMTP_PASSWORD` 운영환경 변수에만 설정하고 코드·설정·로그·테스트·문서에 기록하지 않는다.
- Git 이력 재작성은 별도 승인 없이 수행하지 않는다.
