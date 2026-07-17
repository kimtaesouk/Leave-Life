<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

 

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    require_once '../config/db_config.php';
    
    // 데이터베이스에서 최신 role 정보 가져오기
    $conn = getDBConnection();
    if ($conn) {
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT role, password_updated_at FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
            $_SESSION['role'] = $user_data['role'];
            $_SESSION['password_updated_at'] = $user_data['password_updated_at'];
        }
        $stmt->close();
        $conn->close();
    }
    
    echo json_encode([
        'success' => true,
        'logged_in' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'userid' => $_SESSION['userid'],
            'name' => $_SESSION['name'],
            'email' => $_SESSION['email'],
            'role' => isset($_SESSION['role']) ? $_SESSION['role'] : 0,
            'password_updated_at' => $_SESSION['password_updated_at'] ?? null,
            'login_type' => $_SESSION['login_type'] ?? null
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'logged_in' => false,
        'message' => '로그인되지 않았습니다.'
    ]);
}
?>

