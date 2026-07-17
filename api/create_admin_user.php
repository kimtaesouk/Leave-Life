<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST 요청만 허용됩니다.']);
    exit;
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

if (!isset($_SESSION['role']) || (int)$_SESSION['role'] !== 1 || ($_SESSION['login_type'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
    exit;
}

$userid = isset($_POST['userid']) ? trim($_POST['userid']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$role = isset($_POST['role']) ? (int)$_POST['role'] : 0;

if ($userid === '' || $password === '' || $name === '') {
    echo json_encode(['success' => false, 'message' => '필수 항목이 누락되었습니다.']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => '비밀번호는 8자 이상이어야 합니다.']);
    exit;
}

if (!in_array($role, [2, 3], true)) {
    echo json_encode(['success' => false, 'message' => '관리자 등급이 올바르지 않습니다.']);
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => '데이터베이스 연결에 실패했습니다.']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT id FROM users WHERE userid = ?");
    $stmt->bind_param("s", $userid);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => '이미 사용 중인 ID입니다.']);
        $stmt->close();
        $conn->close();
        exit;
    }
    $stmt->close();

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (userid, password, name, phone, role, password_updated_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssssi", $userid, $passwordHash, $name, $phone, $role);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    echo json_encode(['success' => true, 'message' => '관리자가 생성되었습니다.']);
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '관리자 생성 중 오류가 발생했습니다.']);
    if (isset($conn)) {
        $conn->close();
    }
}
?>
