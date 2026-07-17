<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/db_config.php';

// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST 요청만 허용됩니다.']);
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => '데이터베이스 연결에 실패했습니다.']);
    exit;
}

try {
    // JSON 데이터 받기
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => '잘못된 데이터 형식입니다.']);
        exit;
    }
    
    // 필수 필드 검증
    $required_fields = ['deceasedName', 'relationship', 'deathDate', 'deathLocation', 
                       'sido', 'sigungu', 'funeralPeriod', 'religion', 
                       'funeralProductId', 'contactName', 'contactPhone'];
    
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            echo json_encode(['success' => false, 'message' => "필수 필드가 누락되었습니다: $field"]);
            exit;
        }
    }
    
    // 데이터 준비
    $deceased_name = trim($data['deceasedName']);
    $relationship = trim($data['relationship']);
    $death_date = trim($data['deathDate']);
    $death_location = trim($data['deathLocation']);
    $death_location_other = isset($data['deathLocationOtherText']) ? trim($data['deathLocationOtherText']) : null;
    $expected_visitors = isset($data['expectedVisitors']) && !empty($data['expectedVisitors']) ? (int)$data['expectedVisitors'] : null;
    
    $sido = trim($data['sido']);
    $sigungu = trim($data['sigungu']);
    $funeral_period = trim($data['funeralPeriod']);
    
    $religion = trim($data['religion']);
    $religion_other = isset($data['religionOtherText']) ? trim($data['religionOtherText']) : null;
    
    // 준비된 서비스 (JSON 배열로 저장)
    $prepared_services = null;
    if (isset($data['preparedServices']) && is_array($data['preparedServices']) && count($data['preparedServices']) > 0) {
        $prepared_services = json_encode($data['preparedServices'], JSON_UNESCAPED_UNICODE);
    }
    $other_service_text = isset($data['otherServiceText']) ? trim($data['otherServiceText']) : null;
    
    $burial_site = isset($data['burialSite']) ? trim($data['burialSite']) : null;
    
    $contact_name = trim($data['contactName']);
    $contact_phone = trim($data['contactPhone']);
    $contact_email = isset($data['contactEmail']) ? trim($data['contactEmail']) : null;
    $funeral_product_id = isset($data['funeralProductId']) && $data['funeralProductId'] !== '' ? (int)$data['funeralProductId'] : null;
    $funeral_product_source = $funeral_product_id ? 'user' : null;
    $funeral_hall_id = isset($data['funeralHallId']) && $data['funeralHallId'] !== '' ? (int)$data['funeralHallId'] : 0;
    $funeral_hall_name = isset($data['funeralHallName']) ? trim($data['funeralHallName']) : '';
    $funeral_hall_address = isset($data['funeralHallAddress']) ? trim($data['funeralHallAddress']) : '';
    
    // SQL 쿼리 준비
    $stmt = $conn->prepare("
        INSERT INTO estimate_request 
        (deceased_name, relationship, death_date, death_location, death_location_other, expected_visitors,
         sido, sigungu, funeral_period, religion, religion_other,
         prepared_services, other_service_text, burial_site,
         contact_name, contact_phone, contact_email, funeral_product_id, funeral_product_source)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param("sssssisssssssssssis",
        $deceased_name,
        $relationship,
        $death_date,
        $death_location,
        $death_location_other,
        $expected_visitors,
        $sido,
        $sigungu,
        $funeral_period,
        $religion,
        $religion_other,
        $prepared_services,
        $other_service_text,
        $burial_site,
        $contact_name,
        $contact_phone,
        $contact_email,
        $funeral_product_id,
        $funeral_product_source
    );
    
    if ($stmt->execute()) {
        $estimate_id = $conn->insert_id;
        if ($funeral_hall_id > 0) {
            $selection_payload = [
                'hall_id' => $funeral_hall_id,
                'hall_name' => $funeral_hall_name,
                'hall_address' => $funeral_hall_address,
                'items' => []
            ];
            $selection_json = json_encode($selection_payload, JSON_UNESCAPED_UNICODE);
            $stmt2 = $conn->prepare("
                INSERT INTO estimate_selection (estimate_id, hall_id, selection_json)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE hall_id = VALUES(hall_id), selection_json = VALUES(selection_json)
            ");
            if ($stmt2) {
                $stmt2->bind_param("iis", $estimate_id, $funeral_hall_id, $selection_json);
                $stmt2->execute();
                $stmt2->close();
            }
        }
        echo json_encode([
            'success' => true,
            'message' => '견적 요청이 성공적으로 저장되었습니다.',
            'estimate_id' => $estimate_id
        ]);
    } else {
        throw new Exception('데이터 저장 중 오류가 발생했습니다: ' . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '견적 요청 저장 중 오류가 발생했습니다.']);
    if (isset($conn)) {
        $conn->close();
    }
}
?>
