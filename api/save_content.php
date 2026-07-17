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

// 입력 데이터 받기
$file_path = isset($_POST['file_path']) ? $_POST['file_path'] : '';
$content = isset($_POST['content']) ? $_POST['content'] : '';

// 입력값 검증
if (empty($file_path)) {
    echo json_encode(['success' => false, 'message' => '파일 경로가 필요합니다.']);
    exit;
}

if ($content === '') {
    echo json_encode(['success' => false, 'message' => '내용이 필요합니다.']);
    exit;
}

// 허용된 파일 경로인지 확인 (보안)
$allowed_paths = [
    'about/about.html',
    'service/service.html',
    'estimate/estimate.html',
    'guide/guide.html',
    'support/support.html'
];

if (!in_array($file_path, $allowed_paths)) {
    echo json_encode(['success' => false, 'message' => '허용되지 않은 파일 경로입니다.']);
    exit;
}

// 실제 파일 경로
$real_path = __DIR__ . '/../' . $file_path;

// 파일이 존재하는지 확인
if (!file_exists($real_path)) {
    echo json_encode(['success' => false, 'message' => '파일을 찾을 수 없습니다.']);
    exit;
}

// 파일 쓰기 권한 확인 및 설정
if (!is_writable($real_path)) {
    // 디렉토리 권한 확인 및 설정
    $dir = dirname($real_path);
    if (!is_writable($dir)) {
        @chmod($dir, 0775);
    }
    
    // 파일 권한 변경 시도
    @chmod($real_path, 0666);
    
    // 다시 확인
    if (!is_writable($real_path)) {
        // 파일 소유자 정보 확인
        $fileOwner = @fileowner($real_path);
        $fileGroup = @filegroup($real_path);
        $currentUser = get_current_user();
        
        // 에러 메시지에 상세 정보 포함
        error_log("File write permission error: $real_path, Owner: $fileOwner, Group: $fileGroup");
        
        echo json_encode([
            'success' => false, 
            'message' => '파일 쓰기 권한이 없습니다. 서버 관리자에게 문의하세요.',
            'debug' => '파일: ' . basename($real_path)
        ]);
        exit;
    }
}

try {
    // 임시 파일에 먼저 저장 시도 (권한 문제 회피)
    $temp_path = $real_path . '.tmp_' . time();
    $result = @file_put_contents($temp_path, $content);
    
    if ($result !== false) {
        // 임시 파일 저장 성공 시 원본 파일로 이동
        if (@rename($temp_path, $real_path)) {
            @chmod($real_path, 0666);
            echo json_encode([
                'success' => true,
                'message' => '저장되었습니다.'
            ]);
            exit;
        } else {
            // rename 실패 시 복사 시도
            if (@copy($temp_path, $real_path)) {
                @unlink($temp_path);
                @chmod($real_path, 0666);
                echo json_encode([
                    'success' => true,
                    'message' => '저장되었습니다.'
                ]);
                exit;
            }
            @unlink($temp_path);
        }
    }
    
    // 임시 파일 방식 실패 시 원본 파일에 직접 저장 시도
    $result = @file_put_contents($real_path, $content);
    
    if ($result === false) {
        throw new Exception('파일 저장에 실패했습니다. 파일 권한을 확인해주세요. (소유자: www-data 또는 쓰기 권한 666 필요)');
    }
    
    @chmod($real_path, 0666);
    
    // 저장 확인
    $saved_content = @file_get_contents($real_path);
    if ($saved_content === false || $saved_content !== $content) {
        error_log("Content mismatch after save: $file_path");
    }
    
    echo json_encode([
        'success' => true,
        'message' => '저장되었습니다.',
        'file_size' => strlen($content),
        'saved_size' => strlen($saved_content)
    ]);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

