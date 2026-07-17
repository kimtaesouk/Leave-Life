<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST 요청만 허용됩니다.']);
    exit;
}

$estimate_id = isset($_POST['estimate_id']) ? (int)$_POST['estimate_id'] : 0;
$product_id = isset($_POST['funeral_product_id']) ? (int)$_POST['funeral_product_id'] : 0;

if ($estimate_id <= 0 || $product_id <= 0) {
    echo json_encode(['success' => false, 'message' => '요청 값이 올바르지 않습니다.']);
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => '데이터베이스 연결에 실패했습니다.']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE estimate_request SET funeral_product_id = ?, funeral_product_source = 'admin' WHERE estimate_id = ?");
    $stmt->bind_param("ii", $product_id, $estimate_id);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    echo json_encode(['success' => true, 'message' => '상조 상품이 저장되었습니다.']);
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '상조 상품 저장 중 오류가 발생했습니다.']);
    if (isset($conn)) {
        $conn->close();
    }
}
?>
