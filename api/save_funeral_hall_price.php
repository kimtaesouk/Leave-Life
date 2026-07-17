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

$hall_id = isset($data['hall_id']) ? (int)$data['hall_id'] : 0;
$items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

// 입력값 검증
if ($hall_id <= 0) {
    echo json_encode(['success' => false, 'message' => '장례식장을 선택해주세요.']);
    exit;
}

if (empty($items)) {
    echo json_encode(['success' => false, 'message' => '저장할 항목이 없습니다.']);
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => '데이터베이스 연결에 실패했습니다.']);
    exit;
}

try {
    // 트랜잭션 시작
    $conn->begin_transaction();
    
    // 각 항목 저장
    $success_count = 0;
    $error_messages = [];
    
    foreach ($items as $index => $item) {
        $price_id = isset($item['price_id']) && !empty($item['price_id']) ? (int)$item['price_id'] : 0;
        $category_id = isset($item['category_id']) ? (int)$item['category_id'] : 0;
        $unit = isset($item['unit']) ? trim($item['unit']) : '';
        $default_qty = isset($item['default_qty']) && !empty($item['default_qty']) ? trim($item['default_qty']) : null;
        $price = isset($item['price']) ? trim($item['price']) : '';
        
        // 입력값 검증
        if ($category_id <= 0) {
            $error_messages[] = "항목 " . ($index + 1) . ": 카테고리를 선택해주세요.";
            continue;
        }
        
        if (empty($unit)) {
            $error_messages[] = "항목 " . ($index + 1) . ": 단위를 선택해주세요.";
            continue;
        }
        
        if (empty($price)) {
            $error_messages[] = "항목 " . ($index + 1) . ": 가격을 입력해주세요.";
            continue;
        }
        
        // 가격에서 콤마 제거 및 숫자로 변환
        $price_clean = str_replace(',', '', $price);
        if (!is_numeric($price_clean) || (float)$price_clean <= 0) {
            $error_messages[] = "항목 " . ($index + 1) . ": 가격이 올바르지 않습니다.";
            continue;
        }
        $price_value = (float)$price_clean;
        
        // default_qty 처리 (비어있으면 NULL)
        $default_qty_value = null;
        if (!empty($default_qty)) {
            $default_qty_clean = str_replace(',', '', $default_qty);
            if (is_numeric($default_qty_clean) && (float)$default_qty_clean > 0) {
                $default_qty_value = (float)$default_qty_clean;
            }
        }
        
        // 품명 가져오기 (사용자가 입력한 product_name이 있으면 사용, 없으면 카테고리 이름 사용)
        $product_name = isset($item['product_name']) && !empty($item['product_name']) ? trim($item['product_name']) : '';
        
        if (empty($product_name)) {
            // product_name이 없으면 카테고리 이름 사용
            $cat_stmt = $conn->prepare("SELECT category_name FROM price_category WHERE category_id = ?");
            $cat_stmt->bind_param("i", $category_id);
            $cat_stmt->execute();
            $cat_result = $cat_stmt->get_result();
            if ($cat_result->num_rows > 0) {
                $cat_row = $cat_result->fetch_assoc();
                $product_name = $cat_row['category_name'];
            }
            $cat_stmt->close();
        }
        
        if (empty($product_name)) {
            $error_messages[] = "항목 " . ($index + 1) . ": 품명을 입력해주세요.";
            continue;
        }
        
        // service_content 구성 (단위와 기본 수량 정보)
        $service_content = $unit;
        if ($default_qty_value) {
            $service_content .= ' / ' . $default_qty_value . ' 기준';
        }
        
        // product_type은 단위로 사용
        $product_type = $unit;
        
        // price_id가 있으면 UPDATE, 없으면 INSERT
        if ($price_id > 0) {
            // 기존 항목 수정
            $stmt = $conn->prepare("UPDATE funeral_hall_price SET category_id = ?, product_type = ?, product_name = ?, service_content = ?, price = ?, updated_at = CURRENT_TIMESTAMP WHERE price_id = ?");
            $stmt->bind_param("isssdi", $category_id, $product_type, $product_name, $service_content, $price_value, $price_id);
        } else {
            // 새 항목 추가 (ON DUPLICATE KEY UPDATE 사용)
            // UNIQUE KEY: uk_hall_category_product (hall_id, category_id, product_name(50), service_content(50))
            $stmt = $conn->prepare("INSERT INTO funeral_hall_price (hall_id, category_id, product_type, product_name, service_content, price) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE product_type = VALUES(product_type), product_name = VALUES(product_name), service_content = VALUES(service_content), price = VALUES(price), updated_at = CURRENT_TIMESTAMP");
            $stmt->bind_param("iisssd", $hall_id, $category_id, $product_type, $product_name, $service_content, $price_value);
        }
        
        if ($stmt->execute()) {
            $success_count++;
        } else {
            $error_messages[] = "항목 " . ($index + 1) . ": 저장 실패 - " . $stmt->error;
        }
        
        $stmt->close();
    }
    
    // 모든 항목이 성공한 경우에만 커밋
    if ($success_count === count($items)) {
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => count($items) . '개의 항목이 저장되었습니다.',
            'saved_count' => $success_count
        ]);
    } else {
        // 일부 실패한 경우 롤백
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => '일부 항목 저장에 실패했습니다.',
            'errors' => $error_messages,
            'saved_count' => $success_count,
            'total_count' => count($items)
        ]);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
        $conn->close();
    }
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '저장 중 오류가 발생했습니다: ' . $e->getMessage()]);
}
?>

