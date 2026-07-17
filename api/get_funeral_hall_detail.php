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
    $query = "SELECT hall_id, hall_name, tel, addr_full, representativename, companyno, homepage, businessdate, mortuaycnt, charnelabilitycnt, parkcnt, mealroomyn, superyn, parkyn, waitroomyn, imparyn FROM funeral_hall WHERE hall_id = ? AND is_active = 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $hall_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => '장례식장을 찾을 수 없습니다.']);
        exit;
    }
    
    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'hall' => [
            'hall_id' => $row['hall_id'],
            'hall_name' => $row['hall_name'],
            'tel' => $row['tel'],
            'address' => $row['addr_full'],
            'representativename' => $row['representativename'],
            'companyno' => $row['companyno'],
            'homepage' => $row['homepage'],
            'businessdate' => $row['businessdate'],
            'mortuaycnt' => $row['mortuaycnt'],
            'charnelabilitycnt' => $row['charnelabilitycnt'],
            'parkcnt' => $row['parkcnt'],
            'mealroomyn' => $row['mealroomyn'],
            'superyn' => $row['superyn'],
            'parkyn' => $row['parkyn'],
            'waitroomyn' => $row['waitroomyn'],
            'imparyn' => $row['imparyn']
        ]
    ]);

} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '장례식장 정보 조회 중 오류가 발생했습니다.']);
    if (isset($conn)) {
        $conn->close();
    }
}
?>

