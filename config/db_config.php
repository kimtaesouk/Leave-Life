<?php
// 데이터베이스 연결 설정
define('DB_HOST', '115.68.208.111');
define('DB_USER', 'HanWoori');
define('DB_PASS', 'Sonnaeun!0513');
define('DB_NAME', 'hanwoori');

// 데이터베이스 연결 함수
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("데이터베이스 연결 실패 (에러 코드: " . $conn->connect_errno . "): " . $conn->connect_error);
        }
        
        // UTF-8 인코딩 설정
        $conn->set_charset("utf8mb4");
        
        return $conn;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return null;
    }
}
?>

