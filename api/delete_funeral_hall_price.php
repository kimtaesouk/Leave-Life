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

// JSON 데이터 받기
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => '잘못된 요청 데이터입니다.']);
    exit;
}

$price_id = isset($data['price_id']) ? (int)$data['price_id'] : 0;

// 입력값 검증
if ($price_id <= 0) {
    echo json_encode(['success' => false, 'message' => '가격 ID가 필요합니다.']);
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => '데이터베이스 연결에 실패했습니다.']);
    exit;
}

try {
    // 가격 항목 존재 확인
    $check_stmt = $conn->prepare("SELECT price_id FROM funeral_hall_price WHERE price_id = ?");
    $check_stmt->bind_param("i", $price_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $check_stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => '가격 항목을 찾을 수 없습니다.']);
        exit;
    }
    $check_stmt->close();
    
    // 가격 항목 삭제
    $stmt = $conn->prepare("DELETE FROM funeral_hall_price WHERE price_id = ?");
    $stmt->bind_param("i", $price_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        
        echo json_encode([
            'success' => true,
            'message' => '가격 항목이 삭제되었습니다.'
        ]);
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        
        error_log("Funeral hall price delete error: " . $error);
        echo json_encode(['success' => false, 'message' => '가격 항목 삭제에 실패했습니다: ' . $error]);
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '삭제 중 오류가 발생했습니다.']);
    if (isset($conn)) {
        $conn->close();
    }
}
?>
