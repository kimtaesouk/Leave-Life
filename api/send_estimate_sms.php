<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/api_config.php';

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

$message = "견적서 PDF 링크입니다.\n" . $pdf_url;

$post_fields = [
    'key' => ALIGO_API_KEY,
    'user_id' => ALIGO_USER_ID,
    'sender' => ALIGO_SENDER,
    'receiver' => $phone,
    'msg' => $message
];
if (ALIGO_TESTMODE) {
    $post_fields['testmode_yn'] = 'Y';
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://apis.aligo.in/send/');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
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

echo json_encode(['success' => true, 'message' => '문자를 전송했습니다.']);
?>
