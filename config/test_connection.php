<?php
// 에러 표시 활성화 (개발 환경용)
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>DB 연결 테스트</title></head><body>";
echo "<h2>데이터베이스 연결 테스트</h2>";
echo "<p>PHP 파일이 정상적으로 실행되고 있습니다.</p>";

// 데이터베이스 연결 테스트 파일
if (!file_exists('db_config.php')) {
    die("<p style='color: red;'>✗ db_config.php 파일을 찾을 수 없습니다.</p></body></html>");
}

echo "<p>db_config.php 파일을 찾았습니다.</p>";

require_once 'db_config.php';

echo "<p>db_config.php 파일을 로드했습니다.</p>";

// 상세한 연결 정보 출력
echo "<h3>연결 시도 정보:</h3>";
echo "<ul>";
echo "<li>DB_HOST: " . DB_HOST . "</li>";
echo "<li>DB_USER: " . DB_USER . "</li>";
echo "<li>DB_NAME: " . DB_NAME . "</li>";
echo "</ul>";

// 직접 연결 시도하여 상세 에러 확인
echo "<h3>연결 테스트:</h3>";

// localhost로 먼저 시도
echo "<p>1. localhost로 연결 시도 중...</p>";
try {
    $test_conn_local = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$test_conn_local->connect_error) {
        echo "<p style='color: green;'>✓ localhost 연결 성공!</p>";
        echo "<p>서버 정보: " . $test_conn_local->server_info . "</p>";
        $test_conn_local->close();
    } else {
        echo "<p style='color: orange;'>⚠ localhost 연결 실패: " . $test_conn_local->connect_error . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: orange;'>⚠ localhost 연결 예외: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 원격 호스트로 시도
echo "<p>2. " . DB_HOST . "로 연결 시도 중...</p>";
try {
    $test_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($test_conn->connect_error) {
        throw new Exception("연결 실패: " . $test_conn->connect_error);
    }
    
    echo "<p style='color: green;'>✓ " . DB_HOST . " 연결 성공!</p>";
    echo "<p>서버 정보: " . $test_conn->server_info . "</p>";
    $test_conn->close();
    
} catch (mysqli_sql_exception $e) {
    echo "<p style='color: red;'>✗ MySQL 연결 예외 발생!</p>";
    echo "<p><strong>에러 메시지:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>에러 코드:</strong> " . $e->getCode() . "</p>";
    
    echo "<h3>해결 방법:</h3>";
    echo "<div style='background-color: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<p><strong>MySQL 서버(115.68.208.111)에서 다음 명령을 실행하세요:</strong></p>";
    echo "<p style='color: red;'><strong>모든 호스트에서 접근 가능하도록 사용자를 생성하는 것을 권장합니다:</strong></p>";
    echo "<pre style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
    echo "-- 모든 호스트 허용 (권장)\n";
    echo "CREATE USER IF NOT EXISTS 'HanWoori'@'%' IDENTIFIED BY 'sonnaeun!0513';\n";
    echo "GRANT ALL PRIVILEGES ON hanwoori.* TO 'HanWoori'@'%';\n";
    echo "\n";
    echo "-- 또는 특정 IP만 허용\n";
    echo "CREATE USER IF NOT EXISTS 'HanWoori'@'115.68.208.111' IDENTIFIED BY 'sonnaeun!0513';\n";
    echo "GRANT ALL PRIVILEGES ON hanwoori.* TO 'HanWoori'@'115.68.208.111';\n";
    echo "\n";
    echo "-- 또는 localhost만 허용 (같은 서버인 경우)\n";
    echo "CREATE USER IF NOT EXISTS 'HanWoori'@'localhost' IDENTIFIED BY 'sonnaeun!0513';\n";
    echo "GRANT ALL PRIVILEGES ON hanwoori.* TO 'HanWoori'@'localhost';\n";
    echo "\n";
    echo "FLUSH PRIVILEGES;\n";
    echo "</pre>";
    echo "<p><strong>또는 create_user_all_hosts.sql 파일을 실행하세요 (모든 호스트 허용):</strong></p>";
    echo "<pre style='background-color: #f8f9fa; padding: 15px; border-radius: 5px;'>mysql -u root -p -h 115.68.208.111 < /var/www/html/config/create_user_all_hosts.sql</pre>";
    echo "</div>";
    
    // 포트 확인
    echo "<h3>네트워크 진단:</h3>";
    $port = 3306;
    $connection = @fsockopen(DB_HOST, $port, $errno, $errstr, 5);
    if ($connection) {
        echo "<p style='color: green;'>✓ 포트 $port 접근 가능</p>";
        fclose($connection);
    } else {
        echo "<p style='color: red;'>✗ 포트 $port 접근 불가: $errstr ($errno)</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ 예외 발생: " . htmlspecialchars($e->getMessage()) . "</p>";
}

$conn = getDBConnection();

if ($conn) {
    echo "<p style='color: green; font-size: 1.2em; margin-top: 20px;'>✓✓✓ 데이터베이스 연결 성공! ✓✓✓</p>";
    echo "<p>서버 정보: " . $conn->server_info . "</p>";
    echo "<p>호스트 정보: " . $conn->host_info . "</p>";
    
    // 데이터베이스 목록 확인
    $result = $conn->query("SHOW DATABASES");
    echo "<h3>사용 가능한 데이터베이스:</h3><ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>" . $row['Database'] . "</li>";
    }
    echo "</ul>";
    
    // hanwoori 데이터베이스 확인
    $db_check = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = 'hanwoori'");
    if ($db_check->num_rows > 0) {
        echo "<p style='color: green;'>✓ hanwoori 데이터베이스가 존재합니다.</p>";
        
        // users 테이블 확인
        $conn->select_db('hanwoori');
        $table_check = $conn->query("SHOW TABLES LIKE 'users'");
        if ($table_check->num_rows > 0) {
            echo "<p style='color: green;'>✓ users 테이블이 존재합니다.</p>";
        } else {
            echo "<p style='color: orange;'>⚠ users 테이블이 없습니다. 아래 SQL을 실행해주세요:</p>";
            echo "<pre style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
            echo "USE hanwoori;\n\n";
            echo "CREATE TABLE IF NOT EXISTS users (\n";
            echo "    id INT AUTO_INCREMENT PRIMARY KEY,\n";
            echo "    username VARCHAR(20) NOT NULL UNIQUE,\n";
            echo "    password VARCHAR(255) NOT NULL,\n";
            echo "    name VARCHAR(50) NOT NULL,\n";
            echo "    affiliation VARCHAR(100) NOT NULL,\n";
            echo "    birthdate DATE NOT NULL,\n";
            echo "    phone VARCHAR(20) NOT NULL,\n";
            echo "    email VARCHAR(100) NOT NULL UNIQUE,\n";
            echo "    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
            echo "    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n";
            echo "    INDEX idx_username (username),\n";
            echo "    INDEX idx_email (email)\n";
            echo ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n";
            echo "</pre>";
        }
    } else {
        echo "<p style='color: orange;'>⚠ hanwoori 데이터베이스가 없습니다. setup_user.sql을 실행해주세요.</p>";
    }
    
    $conn->close();
} else {
    echo "<p style='color: red;'>✗ getDBConnection() 함수로 연결 실패!</p>";
    echo "<p>다음을 확인해주세요:</p>";
    echo "<ul>";
    echo "<li>DB_HOST: " . DB_HOST . "</li>";
    echo "<li>DB_USER: " . DB_USER . "</li>";
    echo "<li>DB_NAME: " . DB_NAME . "</li>";
    echo "<li>MySQL 서버가 실행 중인지 확인</li>";
    echo "<li>사용자명과 비밀번호가 올바른지 확인</li>";
    echo "<li>원격 서버인 경우 방화벽 설정 확인</li>";
    echo "</ul>";
}

echo "</body></html>";
?>
