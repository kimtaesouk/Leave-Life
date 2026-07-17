<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../config/db_config.php';

// 로그인 확인
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => '데이터베이스 연결에 실패했습니다.']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT id, userid, name, affiliation, birthdate, phone, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => '사용자 정보를 찾을 수 없습니다.']);
        $stmt->close();
        $conn->close();
        exit;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '사용자 정보를 불러오는 중 오류가 발생했습니다.']);
    if (isset($conn)) {
        $conn->close();
    }
}
?>

