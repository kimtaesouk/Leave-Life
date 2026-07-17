<?php
// 파일 권한 수정 스크립트 (일회성 실행용)
// 브라우저에서 직접 실행하거나 명령줄에서 실행

$files = [
    'about/about.html',
    'service/service.html',
    'estimate/estimate.html',
    'guide/guide.html',
    'support/support.html'
];

echo "<h2>파일 권한 수정</h2>";
echo "<pre>";

foreach ($files as $file) {
    $path = __DIR__ . '/../' . $file;
    $dir = dirname($path);
    
    if (file_exists($path)) {
        // 디렉토리 권한 변경
        if (@chmod($dir, 0775)) {
            echo "✓ 디렉토리 권한 변경 성공: $dir\n";
        } else {
            echo "✗ 디렉토리 권한 변경 실패: $dir\n";
        }
        
        // 파일 권한 변경
        if (@chmod($path, 0666)) {
            echo "✓ 파일 권한 변경 성공: $file (권한: " . substr(sprintf('%o', fileperms($path)), -4) . ")\n";
        } else {
            echo "✗ 파일 권한 변경 실패: $file\n";
        }
        
        // 쓰기 가능 여부 확인
        if (is_writable($path)) {
            echo "  → 쓰기 가능\n";
        } else {
            echo "  → 쓰기 불가능 (소유자 확인 필요)\n";
        }
    } else {
        echo "✗ 파일이 없습니다: $file\n";
    }
    echo "\n";
}

echo "</pre>";
echo "<p><small>참고: 권한 변경이 실패하면 서버 관리자에게 문의하세요.</small></p>";
?>

