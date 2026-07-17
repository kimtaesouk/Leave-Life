-- 견적 요청 테이블 생성
CREATE TABLE IF NOT EXISTS estimate_request (
    estimate_id INT AUTO_INCREMENT PRIMARY KEY,
    -- 기본 정보
    deceased_name VARCHAR(100) NOT NULL COMMENT '환우명 또는 고인 성함',
    relationship VARCHAR(50) NOT NULL COMMENT '관계',
    death_status VARCHAR(10) NOT NULL DEFAULT 'after' COMMENT '현재 상황 (before, after)',
    death_date DATE NULL COMMENT '사망일 (사망 후인 경우)',
    death_location VARCHAR(50) NOT NULL COMMENT '장소',
    death_location_other TEXT COMMENT '기타 장소',
    expected_visitors INT NULL COMMENT '예상 조문객 수',
    -- 지역 선택
    sido VARCHAR(50) NOT NULL COMMENT '시/도',
    sigungu VARCHAR(50) NOT NULL COMMENT '시/군/구',
    funeral_period VARCHAR(20) NOT NULL COMMENT '장례 기간',
    -- 종교
    religion VARCHAR(50) NOT NULL COMMENT '종교',
    religion_other TEXT COMMENT '종교 기타',
    -- 준비된 서비스 (JSON 형식으로 저장)
    prepared_services JSON COMMENT '준비된 서비스 목록',
    other_service_text TEXT COMMENT '기타 준비된 서비스 텍스트',
    -- 장지 선택
    burial_site VARCHAR(50) COMMENT '장지 선택',
    -- 연락처 정보
    contact_name VARCHAR(100) NOT NULL COMMENT '담당자 성함',
    contact_phone VARCHAR(20) NOT NULL COMMENT '연락처',
    contact_email VARCHAR(100) COMMENT '이메일',
    -- 개인정보 처리 동의
    privacy_consent TINYINT(1) NOT NULL DEFAULT 0 COMMENT '개인정보 수집·이용 동의',
    sensitive_info_consent TINYINT(1) NOT NULL DEFAULT 0 COMMENT '견적 상담 연락 동의 (기존 컬럼명 유지)',
    consent_version VARCHAR(20) COMMENT '동의문 버전',
    consented_at DATETIME COMMENT '동의 일시',
    -- 상조 상품
    funeral_product_id INT COMMENT '상조 상품 ID',
    funeral_product_source VARCHAR(10) COMMENT '상조 상품 선택 주체 (user/admin)',
    -- 상태 관리
    status VARCHAR(20) DEFAULT 'pending' COMMENT '상태 (pending, contacted, completed, cancelled)',
    -- 타임스탬프
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_sido_sigungu (sido, sigungu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='견적 요청 테이블';

-- 상조 상품 테이블
CREATE TABLE IF NOT EXISTS funeral_products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    product_type VARCHAR(20) NOT NULL COMMENT '상품 유형 (무빈소, 일반, VIP)',
    product_name VARCHAR(100) NOT NULL COMMENT '상품명',
    price INT NOT NULL COMMENT '가격',
    summary TEXT COMMENT '요약 정보',
    details TEXT COMMENT '상세 정보',
    is_active TINYINT(1) DEFAULT 1 COMMENT '노출 여부',
    sort_order INT DEFAULT 0 COMMENT '정렬 순서',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
    INDEX idx_active (is_active),
    INDEX idx_type (product_type),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='상조 상품';

INSERT INTO funeral_products (product_type, product_name, price, summary, details, is_active, sort_order)
VALUES
    ('무빈소', '무빈소 상품', 100, '무빈소 간소형', '무빈소 간소형 상품 구성', 1, 1),
    ('일반', '일반 상품', 300, '일반형', '일반형 상품 구성', 1, 2),
    ('VIP', 'VIP 상품', 500, '프리미엄형', '프리미엄 상품 구성', 1, 3);

-- 견적서 선택 항목 저장 테이블
CREATE TABLE IF NOT EXISTS estimate_selection (
    selection_id INT AUTO_INCREMENT PRIMARY KEY,
    estimate_id INT NOT NULL COMMENT '견적 요청 ID',
    hall_id INT NOT NULL COMMENT '장례식장 ID',
    selection_json JSON NOT NULL COMMENT '선택 항목/수량 JSON',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
    UNIQUE KEY uniq_estimate (estimate_id),
    INDEX idx_hall_id (hall_id),
    CONSTRAINT fk_estimate_selection_estimate
        FOREIGN KEY (estimate_id) REFERENCES estimate_request(estimate_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='견적 선택 항목 저장';
