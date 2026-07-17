<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/db_config.php';

// 로그인 확인
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST 요청만 허용됩니다.']);
    exit;
}

// 입력 데이터 받기
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$affiliation = isset($_POST['affiliation']) ? trim($_POST['affiliation']) : '';
$birthdate = isset($_POST['birthdate']) ? trim($_POST['birthdate']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$currentPassword = isset($_POST['currentPassword']) ? $_POST['currentPassword'] : '';
$newPassword = isset($_POST['newPassword']) ? $_POST['newPassword'] : '';

// 입력값 검증
$errors = [];

if (empty($name)) {
    $errors[] = '이름을 입력해주세요.';
}

if (empty($affiliation)) {
    $errors[] = '소속 장례식장을 입력해주세요.';
}

if (empty($birthdate)) {
    $errors[] = '생년월일을 입력해주세요.';
} elseif (!preg_match('/^\d{4}\.\d{2}\.\d{2}$/', $birthdate)) {
    $errors[] = '생년월일 형식이 올바르지 않습니다.';
}

if (empty($phone)) {
    $errors[] = '전화번호를 입력해주세요.';
}

if (empty($email)) {
    $errors[] = '이메일을 입력해주세요.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = '올바른 이메일 형식이 아닙니다.';
}

// 비밀번호 변경 시 검증
if (!empty($newPassword)) {
    if (empty($currentPassword)) {
        $errors[] = '현재 비밀번호를 입력해주세요.';
    }
    
    if (strlen($newPassword) < 8) {
        $errors[] = '새 비밀번호는 8자 이상이어야 합니다.';
    }
}

// 에러가 있으면 반환
if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// 데이터베이스 연결
$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => '데이터베이스 연결에 실패했습니다.']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    
    // 현재 사용자 정보 조회
    $stmt = $conn->prepare("SELECT password, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => '사용자 정보를 찾을 수 없습니다.']);
        $stmt->close();
        $conn->close();
        exit;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // 비밀번호 변경 시 현재 비밀번호 확인
    if (!empty($newPassword)) {
        if (!password_verify($currentPassword, $user['password'])) {
            echo json_encode(['success' => false, 'message' => '현재 비밀번호가 올바르지 않습니다.']);
            $conn->close();
            exit;
        }
    }
    
    // 이메일 중복 확인 (다른 사용자가 사용 중인지)
    if ($email !== $user['email']) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => '이미 사용 중인 이메일입니다.']);
            $stmt->close();
            $conn->close();
            exit;
        }
        $stmt->close();
    }
    
    // 생년월일 형식 변환 (YYYY.MM.DD -> YYYY-MM-DD)
    $birthdateFormatted = str_replace('.', '-', $birthdate);
    
    // 정보 업데이트
    if (!empty($newPassword)) {
        // 비밀번호 포함 업데이트
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET name = ?, affiliation = ?, birthdate = ?, phone = ?, email = ?, password = ? WHERE id = ?");
        $stmt->bind_param("ssssssi", $name, $affiliation, $birthdateFormatted, $phone, $email, $passwordHash, $user_id);
    } else {
        // 비밀번호 제외 업데이트
        $stmt = $conn->prepare("UPDATE users SET name = ?, affiliation = ?, birthdate = ?, phone = ?, email = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $name, $affiliation, $birthdateFormatted, $phone, $email, $user_id);
    }
    
    if ($stmt->execute()) {
        // 세션 정보 업데이트
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;
        
        echo json_encode([
            'success' => true,
            'message' => '정보가 수정되었습니다.'
        ]);
    } else {
        throw new Exception("정보 수정 처리 중 오류가 발생했습니다: " . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '정보 수정 처리 중 오류가 발생했습니다.']);
    if (isset($conn)) {
        $conn->close();
    }
}
?>

