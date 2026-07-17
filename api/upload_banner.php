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
if (!isset($_FILES['banner_image'])) {
    echo json_encode(['success' => false, 'message' => '파일이 전송되지 않았습니다.']);
    exit;
}

if ($_FILES['banner_image']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => '파일 크기가 서버 설정을 초과했습니다.',
        UPLOAD_ERR_FORM_SIZE => '파일 크기가 폼 제한을 초과했습니다.',
        UPLOAD_ERR_PARTIAL => '파일이 일부만 업로드되었습니다.',
        UPLOAD_ERR_NO_FILE => '파일이 선택되지 않았습니다.',
        UPLOAD_ERR_NO_TMP_DIR => '임시 폴더가 없습니다.',
        UPLOAD_ERR_CANT_WRITE => '파일 쓰기에 실패했습니다.',
        UPLOAD_ERR_EXTENSION => '파일 업로드가 확장에 의해 중지되었습니다.'
    ];
    $errorCode = $_FILES['banner_image']['error'];
    $errorMsg = isset($errorMessages[$errorCode]) ? $errorMessages[$errorCode] : '알 수 없는 오류 (코드: ' . $errorCode . ')';
    echo json_encode(['success' => false, 'message' => $errorMsg]);
    exit;
}

$file = $_FILES['banner_image'];

// 파일 타입 확인
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => '이미지 파일만 업로드 가능합니다. (JPEG, PNG, GIF, WebP)']);
    exit;
}

// 파일 크기 확인 (10MB 제한)
$maxSize = 10 * 1024 * 1024; // 10MB
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => '파일 크기는 10MB 이하여야 합니다.']);
    exit;
}

// 업로드 디렉토리 (절대 경로 사용)
$uploadDir = dirname(__DIR__) . '/images/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        echo json_encode(['success' => false, 'message' => '이미지 디렉토리를 생성할 수 없습니다.']);
        exit;
    }
}

// 디렉토리 쓰기 권한 확인
if (!is_writable($uploadDir)) {
    echo json_encode(['success' => false, 'message' => '이미지 디렉토리에 쓰기 권한이 없습니다.']);
    exit;
}

// 파일명 생성 (기존 파일명 사용 또는 타임스탬프 사용)
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$fileName = 'banner_1920x600.' . $extension;
$targetPath = $uploadDir . $fileName;

// 기존 파일 백업 (선택사항) - 백업 후 기존 파일 삭제
if (file_exists($targetPath)) {
    $backupName = 'banner_1920x600_backup_' . date('YmdHis') . '.' . $extension;
    $backupPath = $uploadDir . $backupName;
    if (copy($targetPath, $backupPath)) {
        // 백업 성공 후 기존 파일 삭제 (새 파일을 저장하기 위해)
        @unlink($targetPath);
    } else {
        error_log('Backup failed: ' . $targetPath . ' -> ' . $backupPath);
    }
}

// 임시 파일 확인
if (!is_uploaded_file($file['tmp_name'])) {
    echo json_encode([
        'success' => false, 
        'message' => '업로드된 파일이 아닙니다.',
        'debug' => [
            'tmp_name' => $file['tmp_name'],
            'exists' => file_exists($file['tmp_name']),
            'is_uploaded' => is_uploaded_file($file['tmp_name'])
        ]
    ]);
    exit;
}

// 파일 업로드
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // 권한 설정
    chmod($targetPath, 0644);
    
    echo json_encode([
        'success' => true,
        'message' => '이미지가 업로드되었습니다.',
        'file_path' => 'images/' . $fileName,
        'full_path' => $targetPath,
        'timestamp' => time() // 캐시 방지를 위한 타임스탬프
    ]);
} else {
    // 실패 시 상세 정보
    $errorDetails = [
        'tmp_name' => $file['tmp_name'],
        'target_path' => $targetPath,
        'tmp_exists' => file_exists($file['tmp_name']),
        'tmp_readable' => is_readable($file['tmp_name']),
        'is_uploaded' => is_uploaded_file($file['tmp_name']),
        'dir_writable' => is_writable($uploadDir),
        'target_dir_exists' => is_dir(dirname($targetPath)),
        'target_dir_writable' => is_writable(dirname($targetPath)),
        'last_error' => error_get_last()
    ];
    error_log('Upload failed: ' . json_encode($errorDetails));
    
    // copy로 시도 (move_uploaded_file 실패 시)
    if (file_exists($file['tmp_name']) && is_readable($file['tmp_name'])) {
        // 파일 크기 확인
        $fileSize = filesize($file['tmp_name']);
        
        // 디스크 공간 확인
        $freeSpace = disk_free_space($uploadDir);
        
        // 파일명에 문제가 있는지 확인 (특수문자 등)
        $safeFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
        $safeTargetPath = $uploadDir . $safeFileName;
        
        // 원본 경로로 먼저 시도
        if (copy($file['tmp_name'], $targetPath)) {
            chmod($targetPath, 0644);
            @unlink($file['tmp_name']); // 임시 파일 삭제
            echo json_encode([
                'success' => true,
                'message' => '이미지가 업로드되었습니다. (copy 방식 사용)',
                'file_path' => 'images/' . $fileName,
                'timestamp' => time()
            ]);
        } 
        // 안전한 파일명으로 시도
        else if (copy($file['tmp_name'], $safeTargetPath)) {
            chmod($safeTargetPath, 0644);
            @unlink($file['tmp_name']);
            echo json_encode([
                'success' => true,
                'message' => '이미지가 업로드되었습니다. (안전한 파일명 사용)',
                'file_path' => 'images/' . $safeFileName,
                'original_name' => $fileName,
                'timestamp' => time()
            ]);
        } 
        // 모두 실패
        else {
            $copyError = error_get_last();
            echo json_encode([
                'success' => false, 
                'message' => '파일 업로드에 실패했습니다. (copy도 실패)',
                'debug' => array_merge($errorDetails, [
                    'file_size' => $fileSize,
                    'free_space' => $freeSpace,
                    'copy_error' => $copyError,
                    'target_path' => $targetPath,
                    'safe_target_path' => $safeTargetPath,
                    'can_write_target' => is_writable(dirname($targetPath)),
                    'target_exists' => file_exists($targetPath)
                ])
            ]);
        }
    } else {
        echo json_encode([
            'success' => false, 
            'message' => '임시 파일을 읽을 수 없습니다.',
            'debug' => array_merge($errorDetails, [
                'tmp_exists' => file_exists($file['tmp_name']),
                'tmp_readable' => is_readable($file['tmp_name']),
                'tmp_size' => file_exists($file['tmp_name']) ? filesize($file['tmp_name']) : 0
            ])
        ]);
    }
}
?>

