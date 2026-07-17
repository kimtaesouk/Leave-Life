-- 기존 estimate_request 테이블에 사망 전/후 상태를 추가하고,
-- 사망 전 상담은 사망일 없이 저장할 수 있도록 합니다.
ALTER TABLE estimate_request
    ADD COLUMN death_status VARCHAR(10) NOT NULL DEFAULT 'after' COMMENT '현재 상황 (before, after)' AFTER relationship,
    MODIFY COLUMN death_date DATE NULL COMMENT '사망일 (사망 후인 경우)';
