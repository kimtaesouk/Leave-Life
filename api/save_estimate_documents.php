<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST 요청만 허용됩니다.']);
    exit;
}

function parse_size_to_bytes($size) {
    $size = trim($size);
    if ($size === '') return 0;
    $unit = strtolower(substr($size, -1));
    $value = (int)$size;
    switch ($unit) {
        case 'g': return $value * 1024 * 1024 * 1024;
        case 'm': return $value * 1024 * 1024;
        case 'k': return $value * 1024;
        default: return (int)$size;
    }
}

$post_max_size = parse_size_to_bytes(ini_get('post_max_size'));
$content_length = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
if ($post_max_size > 0 && $content_length > $post_max_size) {
    echo json_encode(['success' => false, 'message' => '업로드 용량이 서버 제한을 초과했습니다.']);
    exit;
}

$estimate_id = 0;
if (isset($_POST['estimate_id'])) {
    $estimate_id = (int)$_POST['estimate_id'];
} elseif (isset($_GET['estimate_id'])) {
    $estimate_id = (int)$_GET['estimate_id'];
}
if ($estimate_id <= 0) {
    echo json_encode(['success' => false, 'message' => '견적서 ID가 올바르지 않습니다.']);
    exit;
}

$pdf = isset($_FILES['pdf']) ? $_FILES['pdf'] : null;
$excel = isset($_FILES['excel']) ? $_FILES['excel'] : null;

if (!$pdf || $pdf['error'] !== UPLOAD_ERR_OK) {
    $error = $pdf ? $pdf['error'] : 'NO_FILE';
    echo json_encode(['success' => false, 'message' => 'PDF 파일 업로드에 실패했습니다. (오류 코드: ' . $error . ')']);
    exit;
}

if (!$excel || $excel['error'] !== UPLOAD_ERR_OK) {
    $error = $excel ? $excel['error'] : 'NO_FILE';
    echo json_encode(['success' => false, 'message' => '엑셀 파일 업로드에 실패했습니다. (오류 코드: ' . $error . ')']);
    exit;
}

$upload_dir = __DIR__ . '/../uploads/estimate_docs';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0775, true)) {
        echo json_encode(['success' => false, 'message' => '업로드 디렉토리를 생성할 수 없습니다.']);
        exit;
    }
}
if (!is_writable($upload_dir)) {
    echo json_encode(['success' => false, 'message' => '업로드 디렉토리 권한이 없습니다.']);
    exit;
}

$pdf_filename = "estimate_{$estimate_id}.pdf";
$excel_ext = pathinfo($excel['name'], PATHINFO_EXTENSION);
$excel_ext = $excel_ext ? strtolower($excel_ext) : 'xlsx';
$excel_filename = "estimate_{$estimate_id}.{$excel_ext}";

$pdf_path = $upload_dir . '/' . $pdf_filename;
$excel_path = $upload_dir . '/' . $excel_filename;

if (!move_uploaded_file($pdf['tmp_name'], $pdf_path)) {
    echo json_encode(['success' => false, 'message' => 'PDF 파일 저장에 실패했습니다.']);
    exit;
}

if (!move_uploaded_file($excel['tmp_name'], $excel_path)) {
    echo json_encode(['success' => false, 'message' => '엑셀 파일 저장에 실패했습니다.']);
    exit;
}

$selection_data = isset($_POST['selection_data']) ? trim($_POST['selection_data']) : '';
if ($selection_data !== '') {
    $decoded = json_decode($selection_data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => '선택 항목 데이터가 올바르지 않습니다.']);
        exit;
    }
}

$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => '데이터베이스 연결에 실패했습니다.']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE estimate_request SET status = 'completed' WHERE estimate_id = ?");
    $stmt->bind_param("i", $estimate_id);
    $stmt->execute();
    $stmt->close();

    if ($selection_data !== '') {
        $selection_json = json_encode($decoded, JSON_UNESCAPED_UNICODE);
        $stmt = $conn->prepare("
            INSERT INTO estimate_selection (estimate_id, hall_id, selection_json)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE hall_id = VALUES(hall_id), selection_json = VALUES(selection_json)
        ");
        $hall_id = isset($decoded['hall_id']) ? (int)$decoded['hall_id'] : 0;
        $stmt->bind_param("iis", $estimate_id, $hall_id, $selection_json);
        $stmt->execute();
        $stmt->close();
    }

    $conn->close();

    echo json_encode([
        'success' => true,
        'message' => '견적서 파일이 저장되었습니다.',
        'pdf_url' => "/uploads/estimate_docs/{$pdf_filename}",
        'excel_url' => "/uploads/estimate_docs/{$excel_filename}"
    ]);
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '파일 저장 중 오류가 발생했습니다.']);
    if (isset($conn)) {
        $conn->close();
    }
}
?>
