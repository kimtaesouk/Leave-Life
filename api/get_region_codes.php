<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once '../config/db_config.php';

// region_level 파라미터 받기 (1: 시/도, 2: 시/군/구)
$region_level = isset($_GET['region_level']) ? (int)$_GET['region_level'] : 1;
$parent_code = isset($_GET['parent_code']) ? trim($_GET['parent_code']) : '';

$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => '데이터베이스 연결에 실패했습니다.']);
    exit;
}

try {
    if ($region_level == 1) {
        // 시/도 목록 조회 (region_level = 1)
        $stmt = $conn->prepare("SELECT region_code, region_name FROM region_code WHERE region_level = 1 AND is_active = 1 ORDER BY sort_order, region_name");
        $stmt->execute();
        $result = $stmt->get_result();
    } else if ($region_level == 2 && !empty($parent_code)) {
        // 시/군/구 목록 조회 (region_level = 2, parent_code의 앞 2자리로 시작)
        // 예: parent_code가 '1100000000'이면 '11%'로 시작하는 region_level=2 조회
        $parent_prefix = substr($parent_code, 0, 2);
        $stmt = $conn->prepare("SELECT region_code, region_name FROM region_code WHERE region_level = 2 AND region_code LIKE ? AND is_active = 1 ORDER BY region_name");
        $like_pattern = $parent_prefix . '%';
        $stmt->bind_param("s", $like_pattern);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        echo json_encode(['success' => false, 'message' => '잘못된 파라미터입니다.']);
        $conn->close();
        exit;
    }
    
    $regions = [];
    while ($row = $result->fetch_assoc()) {
        $regions[] = [
            'region_code' => $row['region_code'],
            'region_name' => $row['region_name']
        ];
    }
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'regions' => $regions
    ]);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '데이터 조회 중 오류가 발생했습니다.']);
    if (isset($conn)) {
        $conn->close();
    }
}
?>

