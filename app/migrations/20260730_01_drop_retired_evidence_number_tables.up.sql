-- 자료유형별 Evidence Body의 sort_no 전환이 완료되어 사용하지 않는 전역 채번 저장소를 제거한다.
DROP TABLE IF EXISTS `ledger_evidence_number_histories`;
DROP TABLE IF EXISTS `ledger_evidence_number_sequences`;
