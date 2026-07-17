-- 기존 estimate_request 테이블에 개인정보 동의 기록을 추가합니다.
-- 운영 반영 전 데이터베이스에서 한 번 실행하세요.
ALTER TABLE estimate_request
    ADD COLUMN privacy_consent TINYINT(1) NOT NULL DEFAULT 0 COMMENT '개인정보 수집·이용 동의' AFTER contact_email,
    ADD COLUMN sensitive_info_consent TINYINT(1) NOT NULL DEFAULT 0 COMMENT '견적 상담 연락 동의 (기존 컬럼명 유지)' AFTER privacy_consent,
    ADD COLUMN consent_version VARCHAR(20) NULL COMMENT '동의문 버전' AFTER sensitive_info_consent,
    ADD COLUMN consented_at DATETIME NULL COMMENT '동의 일시' AFTER consent_version;
