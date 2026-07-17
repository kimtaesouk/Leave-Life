<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/db_config.php';

// 관리자 권한 확인
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 1) {
    echo json_encode(['success' => false, 'message' => '관리자 권한이 필요합니다.']);
    exit;
}

// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST 요청만 허용됩니다.']);
    exit;
}

// 입력 데이터 받기
$hall_id = isset($_POST['hall_id']) ? (int)$_POST['hall_id'] : 0;

// 입력값 검증
if ($hall_id <= 0) {
    echo json_encode(['success' => false, 'message' => '장례식장 ID가 필요합니다.']);
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => '데이터베이스 연결에 실패했습니다.']);
    exit;
}

try {
    // 장례식장 존재 확인
    $check_stmt = $conn->prepare("SELECT hall_id FROM funeral_hall WHERE hall_id = ? AND is_active = 1");
    $check_stmt->bind_param("i", $hall_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $check_stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => '장례식장을 찾을 수 없습니다.']);
        exit;
    }
    $check_stmt->close();
    
    // 장례식장 삭제 (is_active = 0으로 설정)
    $stmt = $conn->prepare("UPDATE funeral_hall SET is_active = 0 WHERE hall_id = ?");
    $stmt->bind_param("i", $hall_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        
        echo json_encode([
            'success' => true,
            'message' => '장례식장이 삭제되었습니다.'
        ]);
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        
        error_log("Funeral hall delete error: " . $error);
        echo json_encode(['success' => false, 'message' => '장례식장 삭제에 실패했습니다: ' . $error]);
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '삭제 중 오류가 발생했습니다.']);
    if (isset($conn)) {
        $conn->close();
    }
}
?>
