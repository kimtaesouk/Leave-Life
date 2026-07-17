<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST 요청만 허용됩니다.']);
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => '데이터베이스 연결에 실패했습니다.']);
    exit;
}

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$product_type = isset($_POST['product_type']) ? trim($_POST['product_type']) : '';
$product_name = isset($_POST['product_name']) ? trim($_POST['product_name']) : '';
$price = isset($_POST['price']) ? (int)$_POST['price'] : 0;
$summary = isset($_POST['summary']) ? trim($_POST['summary']) : '';
$details = isset($_POST['details']) ? trim($_POST['details']) : '';
$is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
$sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;

if ($product_type === '' || $product_name === '' || $price <= 0) {
    echo json_encode(['success' => false, 'message' => '필수 항목이 누락되었습니다.']);
    exit;
}

try {
    if ($product_id > 0) {
        $stmt = $conn->prepare("
            UPDATE funeral_products
            SET product_type = ?, product_name = ?, price = ?, summary = ?, details = ?, is_active = ?, sort_order = ?
            WHERE product_id = ?
        ");
        $stmt->bind_param("ssissiii", $product_type, $product_name, $price, $summary, $details, $is_active, $sort_order, $product_id);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO funeral_products (product_type, product_name, price, summary, details, is_active, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssissii", $product_type, $product_name, $price, $summary, $details, $is_active, $sort_order);
    }

    $result = $stmt->execute();
    if (!$result) {
        throw new Exception('저장 중 오류가 발생했습니다: ' . $stmt->error);
    }
    $stmt->close();
    $conn->close();

    echo json_encode(['success' => true, 'message' => '저장되었습니다.']);
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '저장 중 오류가 발생했습니다.']);
    if (isset($conn)) {
        $conn->close();
    }
}
?>
