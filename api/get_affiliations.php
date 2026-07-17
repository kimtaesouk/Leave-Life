<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/db_config.php';

// 데이터베이스 연결
$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => '데이터베이스 연결에 실패했습니다.']);
    exit;
}

try {
    // affiliation 테이블에서 id, affiliation, address, phone 가져오기
    $stmt = $conn->prepare("SELECT id, affiliation, address, phone FROM affiliation ORDER BY affiliation ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $affiliations = [];
    while ($row = $result->fetch_assoc()) {
        $affiliations[] = [
            'id' => $row['id'],
            'affiliation' => $row['affiliation'],
            'address' => $row['address'],
            'phone' => $row['phone']
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'affiliations' => $affiliations
    ]);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '장례식장 목록을 불러오는 중 오류가 발생했습니다.']);
    if (isset($conn)) {
        $conn->close();
    }
}
?>

