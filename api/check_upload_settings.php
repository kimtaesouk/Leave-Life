<?php
// PHP 업로드 설정 확인
header('Content-Type: text/html; charset=utf-8');

echo "<h2>PHP 업로드 설정 확인</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>설정 항목</th><th>값</th></tr>";

$settings = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_file_uploads' => ini_get('max_file_uploads'),
    'file_uploads' => ini_get('file_uploads') ? '활성화' : '비활성화',
    'upload_tmp_dir' => ini_get('upload_tmp_dir') ?: '기본값 사용',
    'memory_limit' => ini_get('memory_limit'),
];

foreach ($settings as $key => $value) {
    echo "<tr><td><strong>$key</strong></td><td>$value</td></tr>";
}

echo "</table>";

echo "<h3>이미지 디렉토리 확인</h3>";
$uploadDir = '../images/';
echo "<p>경로: " . realpath($uploadDir) . "</p>";
echo "<p>존재 여부: " . (is_dir($uploadDir) ? '예' : '아니오') . "</p>";
echo "<p>쓰기 권한: " . (is_writable($uploadDir) ? '예' : '아니오') . "</p>";
echo "<p>권한: " . substr(sprintf('%o', fileperms($uploadDir)), -4) . "</p>";
?>

