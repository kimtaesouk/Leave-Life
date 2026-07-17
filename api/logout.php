<?php
session_start();

// 세션 파괴
$_SESSION = array();

// 세션 쿠키 삭제
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// 쿠키 삭제
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// 세션 파괴
session_destroy();

// JSON 응답
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'message' => '로그아웃되었습니다.']);

// 또는 리다이렉트
// header('Location: ../index.html');
// exit;
?>

