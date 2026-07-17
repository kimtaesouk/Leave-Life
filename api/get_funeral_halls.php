<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../config/db_config.php';

// 관리자 권한 확인 (선택사항)
// if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 1) {
//     echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
//     exit;
// }

$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => '데이터베이스 연결에 실패했습니다.']);
    exit;
}

try {
    // 검색어 파라미터 받기
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // 지역 필터 파라미터 받기
    $sido = isset($_GET['sido']) ? trim($_GET['sido']) : '';
    $sigungu = isset($_GET['sigungu']) ? trim($_GET['sigungu']) : '';
    
    // 페이징 파라미터 받기
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
    $page = max(1, $page);
    $per_page = max(1, min(100, $per_page)); // 최소 1개, 최대 100개
    $offset = ($page - 1) * $per_page;
    
    // 기본 쿼리 (카운트용)
    $count_query = "SELECT COUNT(*) as total FROM funeral_hall WHERE is_active = 1";
    $where_conditions = [];
    $params = [];
    $param_types = "";
    
    // 검색어 조건 추가
    if (!empty($search)) {
        $where_conditions[] = "(hall_name LIKE ? OR tel LIKE ? OR addr_sido LIKE ? OR addr_sigungu LIKE ? OR addr_detail LIKE ?)";
        $search_param = '%' . $conn->real_escape_string($search) . '%';
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $param_types .= "sssss";
    }
    
    // 지역 필터 조건 추가
    if (!empty($sido)) {
        $where_conditions[] = "addr_sido = ?";
        $params[] = $sido;
        $param_types .= "s";
    }
    
    if (!empty($sigungu)) {
        $where_conditions[] = "addr_sigungu = ?";
        $params[] = $sigungu;
        $param_types .= "s";
    }
    
    // WHERE 조건 조합
    if (!empty($where_conditions)) {
        $count_query .= " AND " . implode(" AND ", $where_conditions);
    }
    
    // 전체 개수 조회
    if (!empty($params)) {
        $count_stmt = $conn->prepare($count_query);
        $count_stmt->bind_param($param_types, ...$params);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total_count = $count_result->fetch_assoc()['total'];
        $count_stmt->close();
    } else {
        $count_result = $conn->query($count_query);
        $total_count = $count_result->fetch_assoc()['total'];
    }
    
    // 데이터 조회 쿼리
    $query = "SELECT hall_id, hall_name, tel, addr_sido, addr_sigungu, addr_detail, addr_full, representativename, companyno, homepage, businessdate, mortuaycnt, charnelabilitycnt, parkcnt, mealroomyn, superyn, parkyn, waitroomyn, imparyn FROM funeral_hall WHERE is_active = 1";
    if (!empty($where_conditions)) {
        $query .= " AND " . implode(" AND ", $where_conditions);
    }
    $query .= " ORDER BY hall_name ASC LIMIT ? OFFSET ?";
    
    // 페이징 파라미터 추가
    $query_params = $params;
    $query_param_types = $param_types;
    $query_params[] = $per_page;
    $query_params[] = $offset;
    $query_param_types .= "ii";
    
    $stmt = $conn->prepare($query);
    if (!empty($query_params)) {
        $stmt->bind_param($query_param_types, ...$query_params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $halls = [];
    while ($row = $result->fetch_assoc()) {
        // 시/도부터 포함한 전체 주소 구성
        $full_address = '';
        if (!empty($row['addr_sido'])) {
            $full_address = $row['addr_sido'];
        }
        if (!empty($row['addr_sigungu'])) {
            $full_address .= ' ' . $row['addr_sigungu'];
        }
        if (!empty($row['addr_detail'])) {
            $full_address .= ' ' . $row['addr_detail'];
        }
        $full_address = trim($full_address);
        
        // 시설 정보 텍스트 생성
        $facilities = [];
        if ($row['mealroomyn'] == 1) $facilities[] = '식당';
        if ($row['waitroomyn'] == 1) $facilities[] = '유족대기실';
        if ($row['imparyn'] == 1) $facilities[] = '장애인편의시설';
        if ($row['parkyn'] == 1) $facilities[] = '주차장';
        if ($row['superyn'] == 1) $facilities[] = '매점';
        $facilities_text = !empty($facilities) ? implode(', ', $facilities) : '-';
        
        $halls[] = [
            'hall_id' => $row['hall_id'],
            'hall_name' => $row['hall_name'],
            'tel' => $row['tel'],
            'address' => !empty($full_address) ? $full_address : '-',
            'addr_full' => !empty($row['addr_full']) ? $row['addr_full'] : $full_address,
            'addr_sido' => !empty($row['addr_sido']) ? $row['addr_sido'] : '',
            'addr_sigungu' => !empty($row['addr_sigungu']) ? $row['addr_sigungu'] : '',
            'representativename' => !empty($row['representativename']) ? $row['representativename'] : '',
            'companyno' => !empty($row['companyno']) ? $row['companyno'] : '',
            'homepage' => !empty($row['homepage']) ? $row['homepage'] : '',
            'businessdate' => !empty($row['businessdate']) ? $row['businessdate'] : '',
            'mortuaycnt' => !empty($row['mortuaycnt']) ? $row['mortuaycnt'] : '',
            'charnelabilitycnt' => !empty($row['charnelabilitycnt']) ? $row['charnelabilitycnt'] : '',
            'parkcnt' => !empty($row['parkcnt']) ? $row['parkcnt'] : '',
            'facilities' => $facilities_text
        ];
    }
    
    if (isset($stmt)) {
        $stmt->close();
    }
    
    $total_pages = ceil($total_count / $per_page);
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'halls' => $halls,
        'total_count' => $total_count,
        'total_pages' => $total_pages,
        'current_page' => $page,
        'per_page' => $per_page
    ]);

} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '장례식장 목록 조회 중 오류가 발생했습니다.']);
    if (isset($conn)) {
        $conn->close();
    }
}
?>

