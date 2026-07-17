<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/db_config.php';

// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST 요청만 허용됩니다.']);
    exit;
}

// 입력 데이터 받기
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$remember = isset($_POST['remember']) ? true : false;

// 입력값 검증
if (empty($username)) {
    echo json_encode(['success' => false, 'message' => '아이디를 입력해주세요.']);
    exit;
}

if (empty($password)) {
    echo json_encode(['success' => false, 'message' => '비밀번호를 입력해주세요.']);
    exit;
}

// 데이터베이스 연결
$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => '데이터베이스 연결에 실패했습니다.']);
    exit;
}

try {
    // 사용자 정보 조회
    $stmt = $conn->prepare("SELECT id, userid, password, name, role, password_updated_at FROM users WHERE userid = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => '아이디 또는 비밀번호가 올바르지 않습니다.']);
        $stmt->close();
        $conn->close();
        exit;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // 비밀번호 확인
    if (!password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => '아이디 또는 비밀번호가 올바르지 않습니다.']);
        $conn->close();
        exit;
    }
    
    // 관리자 권한 확인 (role: 1~3)
    $role = isset($user['role']) ? (int)$user['role'] : 0;
    if (!in_array($role, [1, 2, 3], true)) {
        echo json_encode(['success' => false, 'message' => '관리자 권한이 없습니다.']);
        $conn->close();
        exit;
    }

    // 로그인 성공 - 세션 설정
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['userid'] = $user['userid'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $role;
    $_SESSION['password_updated_at'] = $user['password_updated_at'] ?? null;
    $_SESSION['login_type'] = 'admin';
    $_SESSION['logged_in'] = true;
    
    // 로그인 상태 유지 (remember me)
    if ($remember) {
        // 쿠키에 토큰 저장 (1일)
        $token = bin2hex(random_bytes(32));
        setcookie('remember_token', $token, time() + (1 * 24 * 60 * 60), '/');
    }
    
    echo json_encode([
        'success' => true, 
        'message' => '로그인 성공',
        'user' => [
            'id' => $user['id'],
            'userid' => $user['userid'],
            'name' => $user['name'],
            'role' => $role,
            'password_updated_at' => $user['password_updated_at'] ?? null
        ]
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '로그인 처리 중 오류가 발생했습니다.']);
    if (isset($conn)) {
        $conn->close();
    }
}
?>

