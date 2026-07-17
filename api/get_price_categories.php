<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../config/db_config.php';

// 관리자 권한 확인 (선택사항)
// if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 1) {
//     echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
//     exit;
// }

$parent_id = isset($_GET['parent_id']) ? trim($_GET['parent_id']) : '';

$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => '데이터베이스 연결에 실패했습니다.']);
    exit;
}

try {
    // price_category 테이블에는 parent_id가 없으므로 모든 카테고리 조회
    $query = "SELECT category_id, category_code, category_name FROM price_category WHERE is_active = 1 ORDER BY category_code ASC, category_name ASC";
    $result = $conn->query($query);
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = [
            'category_id' => $row['category_id'],
            'category_code' => $row['category_code'],
            'category_name' => $row['category_name']
        ];
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'categories' => $categories
    ]);

} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '카테고리 조회 중 오류가 발생했습니다.']);
    if (isset($conn)) {
        $conn->close();
    }
}
?>

