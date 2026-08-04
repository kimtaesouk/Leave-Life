<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../config/api_config.php';

if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['login_type'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '관리자 로그인이 필요합니다.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST 요청만 허용됩니다.']);
    exit;
}

$estimate_id = isset($_POST['estimate_id']) ? (int)$_POST['estimate_id'] : 0;
$phone = isset($_POST['phone']) ? preg_replace('/[^0-9]/', '', $_POST['phone']) : '';

if ($estimate_id <= 0) {
    echo json_encode(['success' => false, 'message' => '견적서 ID가 올바르지 않습니다.']);
    exit;
}

if ($phone === '') {
    echo json_encode(['success' => false, 'message' => '전화번호가 올바르지 않습니다.']);
    exit;
}

if (empty(ALIGO_API_KEY) || empty(ALIGO_USER_ID) || empty(ALIGO_SENDER)) {
    echo json_encode(['success' => false, 'message' => '문자 API 설정이 필요합니다.']);
    exit;
}

$pdf_path = __DIR__ . '/../uploads/estimate_docs/estimate_' . $estimate_id . '.pdf';
if (!file_exists($pdf_path)) {
    echo json_encode(['success' => false, 'message' => 'PDF 파일이 존재하지 않습니다.']);
    exit;
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$pdf_url = $scheme . '://' . $host . '/uploads/estimate_docs/estimate_' . $estimate_id . '.pdf';

$message = "[리브 라이프]\n요청하신 견적서가 준비되었습니다.\nPDF 확인: " . $pdf_url;

$preview_path = null;
foreach (['jpg', 'png', 'gif'] as $preview_ext) {
    $candidate = __DIR__ . '/../uploads/estimate_docs/estimate_' . $estimate_id . '.' . $preview_ext;
    if (file_exists($candidate)) {
        $preview_path = $candidate;
        break;
    }
}

$post_fields = [
    'key' => ALIGO_API_KEY,
    'user_id' => ALIGO_USER_ID,
    'sender' => ALIGO_SENDER,
    'receiver' => $phone,
    'msg' => $message
];
if ($preview_path) {
    $mime_type = function_exists('mime_content_type') ? mime_content_type($preview_path) : 'image/jpeg';
    $post_fields['title'] = '리브 라이프 견적서';
    $post_fields['msg_type'] = 'MMS';
    $post_fields['image1'] = new CURLFile($preview_path, $mime_type ?: 'image/jpeg', basename($preview_path));
}
if (ALIGO_TESTMODE) {
    $post_fields['testmode_yn'] = 'Y';
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://apis.aligo.in/send/');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $preview_path ? $post_fields : http_build_query($post_fields));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
if ($response === false) {
    $error = curl_error($ch);
    curl_close($ch);
    echo json_encode(['success' => false, 'message' => '문자 전송 중 오류가 발생했습니다: ' . $error]);
    exit;
}
curl_close($ch);

$data = json_decode($response, true);
if (!$data || !isset($data['result_code'])) {
    echo json_encode(['success' => false, 'message' => '문자 전송 응답을 확인할 수 없습니다.']);
    exit;
}

if ((int)$data['result_code'] !== 1) {
    $msg = isset($data['message']) ? $data['message'] : '문자 전송에 실패했습니다.';
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => $preview_path ? '견적서 이미지와 링크를 MMS로 전송했습니다.' : '견적서 링크를 문자로 전송했습니다.',
    'message_type' => $preview_path ? 'MMS' : 'LMS',
    'image_attached' => (bool)$preview_path
]);
?>
