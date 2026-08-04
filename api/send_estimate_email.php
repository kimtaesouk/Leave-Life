<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../config/db_config.php';
require_once __DIR__ . '/smtp_client.php';

if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['login_type'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '관리자 로그인이 필요합니다.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST 요청만 허용됩니다.']);
    exit;
}

$estimate_id = isset($_POST['estimate_id']) ? (int)$_POST['estimate_id'] : 0;
if ($estimate_id <= 0) {
    echo json_encode(['success' => false, 'message' => '견적서 ID가 올바르지 않습니다.']);
    exit;
}

$mail_config = [];
$mail_config_path = __DIR__ . '/../config/email_config.php';
if (is_file($mail_config_path) && is_readable($mail_config_path)) {
    $loaded_mail_config = require $mail_config_path;
    if (is_array($loaded_mail_config)) {
        $mail_config = $loaded_mail_config;
    }
}

$smtp_username = trim((string)(getenv('LEAVE_LIFE_SMTP_USERNAME') ?: ($mail_config['smtp_username'] ?? '')));
$smtp_password = (string)(getenv('LEAVE_LIFE_SMTP_APP_PASSWORD') ?: ($mail_config['smtp_app_password'] ?? ''));
$from_email = trim((string)(getenv('LEAVE_LIFE_MAIL_FROM_EMAIL') ?: ($mail_config['from_email'] ?? $smtp_username)));
$from_name = trim((string)(getenv('LEAVE_LIFE_MAIL_FROM_NAME') ?: ($mail_config['from_name'] ?? '리브 라이프')));
if (!$from_email || !filter_var($from_email, FILTER_VALIDATE_EMAIL) || !$smtp_username || !$smtp_password) {
    echo json_encode(['success' => false, 'message' => '이메일 발송 서버 설정이 필요합니다.']);
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => '데이터베이스 연결에 실패했습니다.']);
    exit;
}

$stmt = $conn->prepare('SELECT contact_email, contact_name, deceased_name FROM estimate_request WHERE estimate_id = ? LIMIT 1');
$stmt->bind_param('i', $estimate_id);
$stmt->execute();
$result = $stmt->get_result();
$estimate = $result ? $result->fetch_assoc() : null;
$stmt->close();
$conn->close();

if (!$estimate) {
    echo json_encode(['success' => false, 'message' => '견적 요청을 찾을 수 없습니다.']);
    exit;
}

$recipient = trim((string)$estimate['contact_email']);
if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => '견적 요청에 저장된 이메일 주소를 확인해주세요.']);
    exit;
}

$pdf_path = __DIR__ . '/../uploads/estimate_docs/estimate_' . $estimate_id . '.pdf';
if (!file_exists($pdf_path)) {
    echo json_encode(['success' => false, 'message' => '먼저 PDF 견적서를 작성해주세요.']);
    exit;
}

$preview_path = null;
$preview_type = null;
foreach (['jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif'] as $extension => $content_type) {
    $candidate = __DIR__ . '/../uploads/estimate_docs/estimate_' . $estimate_id . '.' . $extension;
    if (is_file($candidate) && is_readable($candidate)) {
        $preview_path = $candidate;
        $preview_type = $content_type;
        break;
    }
}
if (!$preview_path) {
    echo json_encode(['success' => false, 'message' => '견적서 미리보기 이미지가 없습니다. 견적서를 다시 완료한 후 발송해주세요.']);
    exit;
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$pdf_url = $scheme . '://' . $host . '/uploads/estimate_docs/estimate_' . $estimate_id . '.pdf';
$subject_text = '[리브 라이프] 요청하신 견적서가 준비되었습니다.';
$attachment_name = 'estimate_' . $estimate_id . '.pdf';
$contact_name = trim((string)$estimate['contact_name']);
$recipient_name = $contact_name ?: '고객';
$plain_message = $recipient_name . "님, 안녕하세요.\r\n"
    . "마음을 먼저 헤아리는 장례 동행 서비스, 리브 라이프입니다.\r\n\r\n"
    . "상담을 통해 요청하신 장례 서비스 견적서가 준비되어 보내드립니다.\r\n"
    . "메일 본문에서 견적서 미리보기를 바로 확인하실 수 있으며, 상세 PDF 원본은 첨부파일로 보내드립니다.\r\n\r\n"
    . "첨부 파일\r\n"
    . "- 상세 견적서 PDF\r\n\r\n"
    . "온라인 확인: " . $pdf_url . "\r\n\r\n"
    . "견적 금액은 상담 시점과 실제 장례 진행 상황에 따라 달라질 수 있습니다.\r\n"
    . "궁금하신 사항은 담당 상담사에게 편하게 문의해주세요.\r\n\r\n"
    . "감사합니다.\r\n"
    . "리브 라이프 장례 서비스 운영센터";
$safe_recipient_name = htmlspecialchars($recipient_name, ENT_QUOTES, 'UTF-8');
$safe_pdf_url = htmlspecialchars($pdf_url, ENT_QUOTES, 'UTF-8');
$html_message = '<!doctype html><html lang="ko"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
    . '<body style="margin:0;padding:0;background:#f3f5f2;color:#2f3d35;font-family:Arial,\'Apple SD Gothic Neo\',\'Noto Sans KR\',sans-serif;">'
    . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f5f2;padding:32px 12px;"><tr><td align="center">'
    . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #dce5df;border-radius:18px;overflow:hidden;">'
    . '<tr><td style="padding:28px 32px;background:#214f3d;color:#ffffff;"><div style="font-size:12px;letter-spacing:2px;color:#d8c7a4;font-weight:700;">LEAVE LIFE</div><div style="margin-top:8px;font-size:23px;font-weight:700;">요청하신 견적서가 준비되었습니다</div></td></tr>'
    . '<tr><td style="padding:32px;line-height:1.75;font-size:15px;">'
    . '<p style="margin:0 0 20px;"><strong>' . $safe_recipient_name . '님</strong>, 안녕하세요.<br>마음을 먼저 헤아리는 장례 동행 서비스, 리브 라이프입니다.</p>'
    . '<p style="margin:0 0 20px;">상담을 통해 요청하신 장례 서비스 견적서가 준비되어 보내드립니다. 아래에서 견적서 내용을 바로 확인하실 수 있으며, 상세 PDF 원본은 메일에 첨부했습니다.</p>'
    . '<div style="margin:24px 0 12px;font-size:16px;font-weight:700;color:#214f3d;">견적서 미리보기</div>'
    . '<div style="margin:0 0 24px;padding:10px;background:#f6f8f6;border:1px solid #dce5df;border-radius:10px;text-align:center;"><img src="cid:leave-life-estimate-preview" alt="리브 라이프 견적서 미리보기" style="display:block;width:100%;max-width:560px;height:auto;margin:0 auto;border:0;"></div>'
    . '<div style="margin:24px 0;padding:16px 20px;background:#f6f8f6;border-left:4px solid #b99a62;border-radius:8px;">상세 항목과 금액은 첨부된 <strong>PDF 견적서</strong>에서 확인하실 수 있습니다.</div>'
    . '<div style="text-align:center;margin:28px 0;"><a href="' . $safe_pdf_url . '" style="display:inline-block;padding:13px 22px;background:#214f3d;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:700;">온라인으로 견적서 확인</a></div>'
    . '<p style="margin:0 0 20px;color:#69776f;font-size:13px;">본 견적 금액은 상담 시점의 예상 금액이며 실제 장례 진행 상황과 선택 항목에 따라 달라질 수 있습니다.</p>'
    . '<p style="margin:0;">궁금하신 사항은 담당 상담사에게 편하게 문의해주세요.<br><br>감사합니다.<br><strong>리브 라이프 장례 서비스 운영센터</strong></p>'
    . '</td></tr><tr><td style="padding:18px 32px;background:#eef2ef;color:#718078;font-size:12px;line-height:1.6;">본 메일은 고객님의 견적 상담 요청에 따라 발송되었습니다.</td></tr>'
    . '</table></td></tr></table></body></html>';

$attachments = [
    [
        'path' => $preview_path,
        'name' => 'estimate_' . $estimate_id . '_preview.' . pathinfo($preview_path, PATHINFO_EXTENSION),
        'type' => $preview_type,
        'disposition' => 'inline',
        'content_id' => 'leave-life-estimate-preview'
    ],
    ['path' => $pdf_path, 'name' => $attachment_name, 'type' => 'application/pdf']
];

$smtp_config = [
    'host' => getenv('LEAVE_LIFE_SMTP_HOST') ?: ($mail_config['smtp_host'] ?? 'smtp.naver.com'),
    'port' => (int)(getenv('LEAVE_LIFE_SMTP_PORT') ?: ($mail_config['smtp_port'] ?? 587)),
    'encryption' => getenv('LEAVE_LIFE_SMTP_ENCRYPTION') ?: ($mail_config['smtp_encryption'] ?? 'tls'),
    'username' => $smtp_username,
    'password' => $smtp_password,
    'timeout' => 20
];

try {
    smtp_send_mail(
        $smtp_config,
        $from_email,
        $from_name,
        $recipient,
        $subject_text,
        $plain_message,
        $html_message,
        $attachments
    );
} catch (Throwable $error) {
    error_log('Leave Life SMTP send failed: ' . $error->getMessage());
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => '이메일 서버에서 발송을 완료하지 못했습니다. SMTP 설정을 확인해주세요.']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => '견적서 미리보기와 PDF를 이메일로 전송했습니다.',
    'recipient' => $recipient,
    'inline_preview' => true,
    'attachments' => ['pdf']
]);
?>
