<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/db_config.php';
require_once '../config/api_config.php';

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

// 입력 데이터 받기
$hall_name = isset($_POST['hall_name']) ? trim($_POST['hall_name']) : '';
$tel = isset($_POST['tel']) ? trim($_POST['tel']) : '';
$full_address = isset($_POST['full_address']) ? trim($_POST['full_address']) : '';
$representativename = isset($_POST['representativename']) ? trim($_POST['representativename']) : null;
$companyno = isset($_POST['companyno']) ? trim($_POST['companyno']) : null;
$homepage = isset($_POST['homepage']) ? trim($_POST['homepage']) : null;
$businessdate = isset($_POST['businessdate']) && !empty($_POST['businessdate']) ? $_POST['businessdate'] : null;
$mortuaycnt = isset($_POST['mortuaycnt']) && $_POST['mortuaycnt'] !== '' ? (int)$_POST['mortuaycnt'] : null;
$charnelabilitycnt = isset($_POST['charnelabilitycnt']) && $_POST['charnelabilitycnt'] !== '' ? (int)$_POST['charnelabilitycnt'] : null;
$parkcnt = isset($_POST['parkcnt']) && $_POST['parkcnt'] !== '' ? (int)$_POST['parkcnt'] : null;
$mealroomyn = isset($_POST['mealroomyn']) && $_POST['mealroomyn'] == '1' ? 1 : 0;
$superyn = isset($_POST['superyn']) && $_POST['superyn'] == '1' ? 1 : 0;
$parkyn = isset($_POST['parkyn']) && $_POST['parkyn'] == '1' ? 1 : 0;
$waitroomyn = isset($_POST['waitroomyn']) && $_POST['waitroomyn'] == '1' ? 1 : 0;
$imparyn = isset($_POST['imparyn']) && $_POST['imparyn'] == '1' ? 1 : 0;

// 입력값 검증
if (empty($hall_name)) {
    echo json_encode(['success' => false, 'message' => '장례식장 이름을 입력해주세요.']);
    exit;
}

if (empty($tel)) {
    echo json_encode(['success' => false, 'message' => '전화번호를 입력해주세요.']);
    exit;
}

// 전화번호 형식 검증 (숫자, 하이픈만 허용)
if (!preg_match('/^[0-9\-]+$/', $tel)) {
    echo json_encode(['success' => false, 'message' => '전화번호 형식이 올바르지 않습니다.']);
    exit;
}

if (empty($full_address)) {
    echo json_encode(['success' => false, 'message' => '주소를 입력해주세요.']);
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => '데이터베이스 연결에 실패했습니다.']);
    exit;
}

try {
    // 위도, 경도 및 주소 정보 조회 (Geocoding API 사용)
    $latitude = null;
    $longitude = null;
    $addr_sido = null;
    $addr_sigungu = null;
    $addr_detail = null;
    
    if (USE_GEOCODING && !empty(KAKAO_REST_API_KEY)) {
        $address_info = getAddressInfoFromGeocoding($full_address);
        if ($address_info) {
            $latitude = $address_info['latitude'];
            $longitude = $address_info['longitude'];
            $addr_sido = $address_info['addr_sido'];
            $addr_sigungu = $address_info['addr_sigungu'];
            $addr_detail = $address_info['addr_detail'];
        } else {
            // API에서 주소를 찾지 못하면 저장하지 않음
            $conn->close();
            echo json_encode(['success' => false, 'message' => '입력한 주소를 찾을 수 없습니다. 주소를 확인해주세요.']);
            exit;
        }
    } else {
        // API 키가 없으면 저장하지 않음
        $conn->close();
        echo json_encode(['success' => false, 'message' => '주소 검색 기능이 활성화되지 않았습니다. 관리자에게 문의하세요.']);
        exit;
    }
    
    // 장례식장 정보 저장
    $stmt = $conn->prepare("INSERT INTO funeral_hall (hall_name, tel, addr_sido, addr_sigungu, addr_detail, addr_full, latitude, longitude, representativename, companyno, homepage, businessdate, mortuaycnt, charnelabilitycnt, parkcnt, mealroomyn, superyn, parkyn, waitroomyn, imparyn, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
    $stmt->bind_param("ssssssddssssiiiiiiiii", $hall_name, $tel, $addr_sido, $addr_sigungu, $addr_detail, $full_address, $latitude, $longitude, $representativename, $companyno, $homepage, $businessdate, $mortuaycnt, $charnelabilitycnt, $parkcnt, $mealroomyn, $superyn, $parkyn, $waitroomyn, $imparyn);
    
    if ($stmt->execute()) {
        $hall_id = $conn->insert_id;
        $stmt->close();
        $conn->close();
        
        echo json_encode([
            'success' => true,
            'message' => '장례식장 정보가 저장되었습니다.',
            'hall_id' => $hall_id
        ]);
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        
        // 중복 키 에러 처리
        if (strpos($error, 'Duplicate entry') !== false) {
            echo json_encode(['success' => false, 'message' => '이미 등록된 장례식장입니다.']);
        } else {
            error_log("Funeral hall save error: " . $error);
            echo json_encode(['success' => false, 'message' => '장례식장 정보 저장에 실패했습니다: ' . $error]);
        }
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '저장 중 오류가 발생했습니다.']);
    if (isset($conn)) {
        $conn->close();
    }
}

/**
 * 시/도 이름을 전체 이름으로 변환
 * @param string $sido 시/도 이름 (예: "서울", "부산")
 * @return string 전체 이름 (예: "서울특별시", "부산광역시")
 */
function convertSidoToFullName($sido) {
    $sido_map = [
        '서울' => '서울특별시',
        '부산' => '부산광역시',
        '대구' => '대구광역시',
        '인천' => '인천광역시',
        '광주' => '광주광역시',
        '대전' => '대전광역시',
        '울산' => '울산광역시',
        '세종' => '세종특별자치시',
        '경기' => '경기도',
        '강원' => '강원특별자치도',
        '충북' => '충청북도',
        '충남' => '충청남도',
        '전북' => '전북특별자치도',
        '전남' => '전라남도',
        '경북' => '경상북도',
        '경남' => '경상남도',
        '제주' => '제주특별자치도'
    ];
    
    // 매핑에 있으면 전체 이름 반환, 없으면 원본 반환
    return isset($sido_map[$sido]) ? $sido_map[$sido] : $sido;
}

/**
 * 카카오 Geocoding API를 사용하여 주소로부터 위도, 경도, 주소 정보를 조회
 * @param string $address 검색할 주소
 * @return array|null ['latitude' => float, 'longitude' => float, 'addr_sido' => string, 'addr_sigungu' => string, 'addr_emd' => string, 'addr_detail' => string] 또는 null
 */
function getAddressInfoFromGeocoding($address) {
    if (empty(KAKAO_REST_API_KEY)) {
        return null;
    }
    
    $api_url = 'https://dapi.kakao.com/v2/local/search/address.json';
    $query_string = http_build_query([
        'query' => $address
    ]);
    $request_url = $api_url . '?' . $query_string;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $request_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: KakaoAK ' . KAKAO_REST_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        error_log("Kakao Geocoding API error: HTTP $http_code - $response");
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['documents']) || empty($data['documents'])) {
        error_log("Kakao Geocoding API: No results for address: $address");
        return null;
    }
    
    // 첫 번째 결과 사용
    $result = $data['documents'][0];
    
    if (!isset($result['y']) || !isset($result['x'])) {
        return null;
    }
    
    // 위도, 경도
    $latitude = (float)$result['y'];
    $longitude = (float)$result['x'];
    
    // 주소 정보 추출 (address 또는 road_address에서 가져옴)
    $addr_sido = null;
    $addr_sigungu = null;
    $addr_emd = null;
    $addr_detail = '';
    
    // 주소 정보는 address(지번주소) 또는 road_address(도로명주소)에서 가져올 수 있음
    // address를 우선 사용, 없으면 road_address 사용
    $address_data = isset($result['address']) ? $result['address'] : (isset($result['road_address']) ? $result['road_address'] : null);
    
    if ($address_data) {
        // 1단계: 시/도 (전체 이름으로 변환)
        if (isset($address_data['region_1depth_name'])) {
            $addr_sido = convertSidoToFullName($address_data['region_1depth_name']);
        }
        
        // 2단계: 시/군/구
        if (isset($address_data['region_2depth_name'])) {
            $addr_sigungu = $address_data['region_2depth_name'];
        }
        
        // 3단계: 읍/면/동
        if (isset($address_data['region_3depth_name'])) {
            $addr_emd = $address_data['region_3depth_name'];
        }
    }
    
    // addr_detail 구성: 도로명주소(구주소) 형식
    // 예: "테헤란로 123 (역삼동 648-23)"
    if (isset($result['road_address'])) {
        // 도로명 주소 구성
        $road_name = $result['road_address']['road_name'] ?? '';
        $main_building_no = $result['road_address']['main_building_no'] ?? '';
        $sub_building_no = $result['road_address']['sub_building_no'] ?? '';
        
        $road_address_str = $road_name;
        if (!empty($main_building_no)) {
            $road_address_str .= ' ' . $main_building_no;
            if (!empty($sub_building_no)) {
                $road_address_str .= '-' . $sub_building_no;
            }
        }
        
        // 구주소 구성
        if (isset($result['address'])) {
            $emd_name = $result['address']['region_3depth_name'] ?? '';
            $main_address_no = $result['address']['main_address_no'] ?? '';
            $sub_address_no = $result['address']['sub_address_no'] ?? '';
            
            $old_address_str = '';
            if (!empty($emd_name)) {
                $old_address_str = $emd_name;
                if (!empty($main_address_no)) {
                    $old_address_str .= ' ' . $main_address_no;
                    if (!empty($sub_address_no)) {
                        $old_address_str .= '-' . $sub_address_no;
                    }
                }
            }
            
            // 도로명주소(구주소) 형식으로 조합
            if (!empty($road_address_str)) {
                $addr_detail = $road_address_str;
                if (!empty($old_address_str)) {
                    $addr_detail .= ' (' . $old_address_str . ')';
                }
            } else if (!empty($old_address_str)) {
                // 도로명 주소가 없으면 구주소만 사용
                $addr_detail = $old_address_str;
            }
        } else {
            // 구주소가 없으면 도로명 주소만 사용
            $addr_detail = $road_address_str;
        }
    } else if (isset($result['address'])) {
        // 도로명 주소가 없으면 구주소만 사용
        $emd_name = $result['address']['region_3depth_name'] ?? '';
        $main_address_no = $result['address']['main_address_no'] ?? '';
        $sub_address_no = $result['address']['sub_address_no'] ?? '';
        
        if (!empty($emd_name)) {
            $addr_detail = $emd_name;
            if (!empty($main_address_no)) {
                $addr_detail .= ' ' . $main_address_no;
                if (!empty($sub_address_no)) {
                    $addr_detail .= '-' . $sub_address_no;
                }
            }
        }
    }
    
    return [
        'latitude' => $latitude,
        'longitude' => $longitude,
        'addr_sido' => $addr_sido,
        'addr_sigungu' => $addr_sigungu,
        'addr_emd' => $addr_emd,
        'addr_detail' => $addr_detail
    ];
}
?>

