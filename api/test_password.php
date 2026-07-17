<?php
// 비밀번호 해시 테스트 및 생성
header('Content-Type: text/html; charset=utf-8');

$test_password = 'admin123';

echo "<h2>비밀번호 해시 테스트</h2>";
echo "<p>테스트 비밀번호: " . htmlspecialchars($test_password) . "</p>";

// 새 해시 생성
$new_hash = password_hash($test_password, PASSWORD_DEFAULT);
echo "<h3>새로 생성된 해시:</h3>";
echo "<pre>" . htmlspecialchars($new_hash) . "</pre>";

// 기존 해시와 비교
$existing_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
echo "<h3>기존 해시와 비교:</h3>";
echo "<p>기존 해시: " . htmlspecialchars($existing_hash) . "</p>";

$verify_result = password_verify($test_password, $existing_hash);
echo "<p>비밀번호 검증 결과: " . ($verify_result ? "<strong style='color: green;'>성공</strong>" : "<strong style='color: red;'>실패</strong>") . "</p>";

// 데이터베이스에서 실제 사용자 확인
require_once '../config/db_config.php';
$conn = getDBConnection();

if ($conn) {
    echo "<h3>데이터베이스에서 사용자 확인:</h3>";
    
    $stmt = $conn->prepare("SELECT id, username, password, name, email FROM users WHERE username = ?");
    $stmt->bind_param("s", $test_username = 'admin');
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo "<p style='color: green;'>✓ 사용자 'admin' 발견</p>";
        echo "<pre>";
        echo "ID: " . $user['id'] . "\n";
        echo "Username: " . $user['username'] . "\n";
        echo "Password Hash: " . substr($user['password'], 0, 50) . "...\n";
        echo "Name: " . $user['name'] . "\n";
        echo "Email: " . $user['email'] . "\n";
        echo "</pre>";
        
        // 비밀번호 검증 테스트
        echo "<h3>데이터베이스 해시로 비밀번호 검증:</h3>";
        $db_verify = password_verify($test_password, $user['password']);
        echo "<p>비밀번호 'admin123' 검증 결과: " . ($db_verify ? "<strong style='color: green;'>성공</strong>" : "<strong style='color: red;'>실패</strong>") . "</p>";
        
        if (!$db_verify) {
            echo "<h3>해결 방법:</h3>";
            echo "<p>데이터베이스의 비밀번호 해시를 업데이트하세요:</p>";
            echo "<pre style='background-color: #f8f9fa; padding: 15px; border-radius: 5px;'>";
            echo "UPDATE users SET password = '" . $new_hash . "' WHERE username = 'admin';\n";
            echo "</pre>";
        }
    } else {
        echo "<p style='color: red;'>✗ 사용자 'admin'을 찾을 수 없습니다.</p>";
    }
    
    $stmt->close();
    $conn->close();
} else {
    echo "<p style='color: red;'>데이터베이스 연결 실패</p>";
}
?>

