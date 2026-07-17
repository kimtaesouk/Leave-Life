<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/db_config.php';

$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => '데이터베이스 연결에 실패했습니다.']);
    exit;
}

$include_inactive = isset($_GET['include_inactive']) && $_GET['include_inactive'] === '1';

try {
    $query = "SELECT * FROM funeral_products";
    if (!$include_inactive) {
        $query .= " WHERE is_active = 1";
    }
    $query .= " ORDER BY sort_order ASC, product_id ASC";

    $result = $conn->query($query);
    $products = [];

    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'product_id' => (int)$row['product_id'],
            'product_type' => $row['product_type'],
            'product_name' => $row['product_name'],
            'price' => (int)$row['price'],
            'summary' => $row['summary'],
            'details' => $row['details'],
            'is_active' => (int)$row['is_active'],
            'sort_order' => (int)$row['sort_order'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }

    $conn->close();

    echo json_encode(['success' => true, 'products' => $products]);
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '상조 상품을 불러오는 중 오류가 발생했습니다.']);
    if (isset($conn)) {
        $conn->close();
    }
}
?>
