-- 운영 공식 코드와 정렬 이력은 자동 역변환하지 않는 forward-only Migration이다.
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FEES_AND_COMMISSIONS 공식 코드는 forward-only 정책으로 Down Migration을 지원하지 않습니다.';
