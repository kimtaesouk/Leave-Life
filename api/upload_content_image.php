<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// 관리자 권한 확인
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

// role 확인 (1 = 관리자)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 1) {
    echo json_encode(['success' => false, 'message' => '관리자 권한이 필요합니다.']);
    exit;
}

// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST 요청만 허용됩니다.']);
    exit;
}

// 파일 업로드 확인
if (!isset($_FILES['image'])) {
    echo json_encode(['success' => false, 'message' => '파일이 전송되지 않았습니다.']);
    exit;
}

if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => '파일 크기가 서버 설정을 초과했습니다.',
        UPLOAD_ERR_FORM_SIZE => '파일 크기가 폼 제한을 초과했습니다.',
        UPLOAD_ERR_PARTIAL => '파일이 일부만 업로드되었습니다.',
        UPLOAD_ERR_NO_FILE => '파일이 선택되지 않았습니다.',
        UPLOAD_ERR_NO_TMP_DIR => '임시 폴더가 없습니다.',
        UPLOAD_ERR_CANT_WRITE => '파일 쓰기에 실패했습니다.',
        UPLOAD_ERR_EXTENSION => '파일 업로드가 확장에 의해 중지되었습니다.'
    ];
    $errorCode = $_FILES['image']['error'];
    $errorMsg = isset($errorMessages[$errorCode]) ? $errorMessages[$errorCode] : '알 수 없는 오류';
    echo json_encode(['success' => false, 'message' => $errorMsg]);
    exit;
}

$file = $_FILES['image'];

// 파일 타입 확인
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => '이미지 파일만 업로드 가능합니다. (JPEG, PNG, GIF, WebP)']);
    exit;
}

// 파일 크기 제한 (10MB)
$maxSize = 10 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => '파일 크기는 10MB를 초과할 수 없습니다.']);
    exit;
}

// 업로드 디렉토리
$uploadDir = __DIR__ . '/../images/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

// 고유한 파일명 생성
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'content_' . time() . '_' . uniqid() . '.' . $extension;
$targetPath = $uploadDir . $filename;

// 파일 이동
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // 파일 권한 설정
    chmod($targetPath, 0644);
    
    echo json_encode([
        'success' => true,
        'message' => '이미지가 업로드되었습니다.',
        'filename' => $filename,
        'url' => 'images/' . $filename
    ]);
} else {
    echo json_encode(['success' => false, 'message' => '파일 업로드에 실패했습니다.']);
}
?>

