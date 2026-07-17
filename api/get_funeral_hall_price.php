<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../config/db_config.php';

// 관리자 권한 확인 (선택사항)
// if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 1) {
//     echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
//     exit;
// }

$hall_id = isset($_GET['hall_id']) ? (int)$_GET['hall_id'] : 0;

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
    // 장례식장 가격 정보 조회 (카테고리별로 그룹화)
    $query = "
        SELECT 
            fhp.price_id,
            fhp.hall_id,
            fhp.category_id,
            fhp.product_type,
            fhp.product_name,
            fhp.service_content,
            fhp.price,
            pc.category_code,
            pc.category_name
        FROM funeral_hall_price fhp
        INNER JOIN price_category pc ON fhp.category_id = pc.category_id
        WHERE fhp.hall_id = ? AND pc.is_active = 1
        ORDER BY pc.category_code ASC, pc.category_name ASC, fhp.product_name ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $hall_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $categories = [];
    
    while ($row = $result->fetch_assoc()) {
        $category_code = $row['category_code'] ? $row['category_code'] : 'OTHER';
        $category_name = $row['category_name'] ? $row['category_name'] : '기타';
        
        // 카테고리 코드별로 그룹화
        if (!isset($categories[$category_code])) {
            $categories[$category_code] = [
                'category_code' => $category_code,
                'category_name' => $category_name,
                'items' => []
            ];
        }
        
        // service_content에서 단위 정보 추출 (예: "1회 / 2시간 기준")
        $unit = $row['product_type'] ? $row['product_type'] : '';
        $default_qty = null;
        if ($row['service_content']) {
            // service_content에서 숫자 추출 시도
            if (preg_match('/(\d+)\s*(회|일|시간|kg|박스|개)/', $row['service_content'], $matches)) {
                $default_qty = $matches[1];
            }
        }
        
        $categories[$category_code]['items'][] = [
            'price_id' => $row['price_id'],
            'category_id' => $row['category_id'],
            'category_name' => $row['category_name'],
            'product_name' => $row['product_name'] ? $row['product_name'] : $row['category_name'],
            'product_type' => $row['product_type'],
            'service_content' => $row['service_content'],
            'unit' => $row['product_type'] ? $row['product_type'] : '',
            'default_qty' => $default_qty,
            'price' => $row['price']
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    // 배열을 인덱스 순서대로 정렬
    $categories_array = array_values($categories);
    
    echo json_encode([
        'success' => true,
        'hall_id' => $hall_id,
        'categories' => $categories_array
    ]);

} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '가격 정보 조회 중 오류가 발생했습니다.']);
    if (isset($conn)) {
        $conn->close();
    }
}
?>
