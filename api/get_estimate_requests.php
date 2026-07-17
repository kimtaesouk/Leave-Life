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
    // 특정 ID 조회
    $estimate_id = isset($_GET['estimate_id']) ? (int)$_GET['estimate_id'] : 0;
    
    if ($estimate_id > 0) {
        // 특정 견적서 조회
        $stmt = $conn->prepare("SELECT * FROM estimate_request WHERE estimate_id = ?");
        $stmt->bind_param("i", $estimate_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            // JSON 필드 파싱
            $prepared_services = null;
            if (!empty($row['prepared_services'])) {
                $prepared_services = json_decode($row['prepared_services'], true);
            }
            
            $doc_dir = __DIR__ . '/../uploads/estimate_docs';
            $pdf_filename = "estimate_{$row['estimate_id']}.pdf";
            $xlsx_filename = "estimate_{$row['estimate_id']}.xlsx";
            $csv_filename = "estimate_{$row['estimate_id']}.csv";
            $pdf_url = file_exists($doc_dir . '/' . $pdf_filename) ? "/uploads/estimate_docs/{$pdf_filename}" : null;
            $excel_url = null;
            if (file_exists($doc_dir . '/' . $xlsx_filename)) {
                $excel_url = "/uploads/estimate_docs/{$xlsx_filename}";
            } elseif (file_exists($doc_dir . '/' . $csv_filename)) {
                $excel_url = "/uploads/estimate_docs/{$csv_filename}";
            }

            $estimate = [
                'estimate_id' => $row['estimate_id'],
                'deceased_name' => $row['deceased_name'],
                'relationship' => $row['relationship'],
                'death_date' => $row['death_date'],
                'death_location' => $row['death_location'],
                'death_location_other' => $row['death_location_other'],
                'expected_visitors' => isset($row['expected_visitors']) ? $row['expected_visitors'] : null,
                'sido' => $row['sido'],
                'sigungu' => $row['sigungu'],
                'funeral_period' => $row['funeral_period'],
                'religion' => $row['religion'],
                'religion_other' => $row['religion_other'],
                'prepared_services' => $prepared_services,
                'other_service_text' => $row['other_service_text'],
                'burial_site' => $row['burial_site'],
                'contact_name' => $row['contact_name'],
                'contact_phone' => $row['contact_phone'],
                'contact_email' => $row['contact_email'],
                'funeral_product_id' => isset($row['funeral_product_id']) ? (int)$row['funeral_product_id'] : null,
                'funeral_product_source' => $row['funeral_product_source'] ?? null,
                'status' => $row['status'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'pdf_url' => $pdf_url,
                'excel_url' => $excel_url
            ];
            
            $stmt->close();
            $conn->close();
            
            echo json_encode([
                'success' => true,
                'estimate' => $estimate
            ]);
            exit;
        } else {
            $stmt->close();
            echo json_encode(['success' => false, 'message' => '견적서를 찾을 수 없습니다.']);
            $conn->close();
            exit;
        }
    }
    
    // 페이징 파라미터 받기
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
    $page = max(1, $page);
    $per_page = max(1, min(100, $per_page)); // 최소 1개, 최대 100개
    $offset = ($page - 1) * $per_page;
    
    // 검색 파라미터 받기
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    
    // 전체 개수 조회
    $count_query = "SELECT COUNT(*) as total FROM estimate_request WHERE 1=1";
    $where_conditions = [];
    $params = [];
    $param_types = "";
    
    // 검색 조건 추가
    if (!empty($search)) {
        $where_conditions[] = "(deceased_name LIKE ? OR contact_name LIKE ? OR contact_phone LIKE ? OR contact_email LIKE ?)";
        $search_param = '%' . $conn->real_escape_string($search) . '%';
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $param_types .= "ssss";
    }
    
    // 상태 필터 조건 추가
    if (!empty($status)) {
        $where_conditions[] = "status = ?";
        $params[] = $status;
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
    $query = "SELECT * FROM estimate_request WHERE 1=1";
    if (!empty($where_conditions)) {
        $query .= " AND " . implode(" AND ", $where_conditions);
    }
    $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    
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
    
    $estimates = [];
    while ($row = $result->fetch_assoc()) {
        // JSON 필드 파싱
        $prepared_services = null;
        if (!empty($row['prepared_services'])) {
            $prepared_services = json_decode($row['prepared_services'], true);
        }
        
        $doc_dir = __DIR__ . '/../uploads/estimate_docs';
        $pdf_filename = "estimate_{$row['estimate_id']}.pdf";
        $xlsx_filename = "estimate_{$row['estimate_id']}.xlsx";
        $csv_filename = "estimate_{$row['estimate_id']}.csv";
        $pdf_url = file_exists($doc_dir . '/' . $pdf_filename) ? "/uploads/estimate_docs/{$pdf_filename}" : null;
        $excel_url = null;
        if (file_exists($doc_dir . '/' . $xlsx_filename)) {
            $excel_url = "/uploads/estimate_docs/{$xlsx_filename}";
        } elseif (file_exists($doc_dir . '/' . $csv_filename)) {
            $excel_url = "/uploads/estimate_docs/{$csv_filename}";
        }

        $estimates[] = [
            'estimate_id' => $row['estimate_id'],
            'deceased_name' => $row['deceased_name'],
            'relationship' => $row['relationship'],
            'death_date' => $row['death_date'],
            'death_location' => $row['death_location'],
            'death_location_other' => $row['death_location_other'],
            'expected_visitors' => isset($row['expected_visitors']) ? $row['expected_visitors'] : null,
            'sido' => $row['sido'],
            'sigungu' => $row['sigungu'],
            'funeral_period' => $row['funeral_period'],
            'religion' => $row['religion'],
            'religion_other' => $row['religion_other'],
            'prepared_services' => $prepared_services,
            'other_service_text' => $row['other_service_text'],
            'burial_site' => $row['burial_site'],
            'contact_name' => $row['contact_name'],
            'contact_phone' => $row['contact_phone'],
            'contact_email' => $row['contact_email'],
            'funeral_product_id' => isset($row['funeral_product_id']) ? (int)$row['funeral_product_id'] : null,
            'funeral_product_source' => $row['funeral_product_source'] ?? null,
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'pdf_url' => $pdf_url,
            'excel_url' => $excel_url
        ];
    }
    
    if (isset($stmt)) {
        $stmt->close();
    }
    
    $total_pages = ceil($total_count / $per_page);
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'estimates' => $estimates,
        'total_count' => $total_count,
        'total_pages' => $total_pages,
        'current_page' => $page,
        'per_page' => $per_page
    ]);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '견적 요청 목록 조회 중 오류가 발생했습니다.']);
    if (isset($conn)) {
        $conn->close();
    }
}
?>
