<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/db_config.php';

$estimate_id = isset($_GET['estimate_id']) ? (int)$_GET['estimate_id'] : 0;
if ($estimate_id <= 0) {
    echo json_encode(['success' => false, 'message' => '견적서 ID가 올바르지 않습니다.']);
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => '데이터베이스 연결에 실패했습니다.']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT selection_json FROM estimate_selection WHERE estimate_id = ?");
    $stmt->bind_param("i", $estimate_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => '선택 항목 데이터가 없습니다.']);
        $stmt->close();
        $conn->close();
        exit;
    }

    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();

    $data = json_decode($row['selection_json'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => '선택 항목 데이터 형식이 올바르지 않습니다.']);
        exit;
    }

    echo json_encode(['success' => true, 'selection' => $data]);
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '선택 항목 데이터를 불러오는 중 오류가 발생했습니다.']);
    if (isset($conn)) {
        $conn->close();
    }
}
?>
