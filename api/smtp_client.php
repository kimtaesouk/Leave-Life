<?php

/**
 * Minimal authenticated SMTP client for environments where Composer packages
 * are not available. Credentials are supplied by the caller and are never
 * written to logs by this file.
 */
function smtp_read_response($socket)
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }

    if ($response === '') {
        throw new RuntimeException('SMTP server closed the connection unexpectedly.');
    }

    return $response;
}

function smtp_expect($socket, array $expected_codes)
{
    $response = smtp_read_response($socket);
    $code = (int)substr($response, 0, 3);

    if (!in_array($code, $expected_codes, true)) {
        throw new RuntimeException('SMTP server returned code ' . $code . '.');
    }

    return $response;
}

function smtp_command($socket, $command, array $expected_codes)
{
    smtp_write_all($socket, $command . "\r\n");

    return smtp_expect($socket, $expected_codes);
}

function smtp_write_all($socket, $data)
{
    $length = strlen($data);
    $offset = 0;

    while ($offset < $length) {
        $written = fwrite($socket, substr($data, $offset));
        if ($written === false || $written === 0) {
            throw new RuntimeException('Unable to write to SMTP server.');
        }
        $offset += $written;
    }
}

function smtp_encode_header($value)
{
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function smtp_build_message($from_email, $from_name, $to_email, $subject, $plain_message, $html_message, array $attachments)
{
    $boundary = '=_LeaveLife_' . bin2hex(random_bytes(12));
    $alternative_boundary = '=_LeaveLifeAlt_' . bin2hex(random_bytes(12));
    $related_boundary = '=_LeaveLifeRelated_' . bin2hex(random_bytes(12));
    $safe_from_name = str_replace(["\r", "\n"], '', $from_name);
    $safe_to_email = str_replace(["\r", "\n"], '', $to_email);
    $host = preg_replace('/[^a-z0-9.-]/i', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';

    $headers = [
        'Date: ' . date(DATE_RFC2822),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $host . '>',
        'From: ' . smtp_encode_header($safe_from_name) . ' <' . $from_email . '>',
        'To: <' . $safe_to_email . '>',
        'Subject: ' . smtp_encode_header($subject),
        'MIME-Version: 1.0',
        'Content-Type: multipart/mixed; boundary="' . $boundary . '"'
    ];

    $alternative_part = 'Content-Type: multipart/alternative; boundary="' . $alternative_boundary . "\"\r\n\r\n"
        . '--' . $alternative_boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($plain_message)) . "\r\n"
        . '--' . $alternative_boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html_message)) . "\r\n"
        . '--' . $alternative_boundary . "--\r\n";

    $inline_parts = '';
    $attachment_parts = '';

    foreach ($attachments as $attachment) {
        $path = $attachment['path'] ?? '';
        if (!$path || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Email attachment is not readable.');
        }

        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)($attachment['name'] ?? basename($path)));
        $content_type = (string)($attachment['type'] ?? 'application/octet-stream');
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Unable to read email attachment.');
        }

        $disposition = strtolower((string)($attachment['disposition'] ?? 'attachment'));
        if ($disposition === 'inline') {
            $content_id = preg_replace('/[^a-zA-Z0-9._@-]/', '', (string)($attachment['content_id'] ?? ''));
            if ($content_id === '') {
                throw new RuntimeException('Inline email image requires a content ID.');
            }
            $inline_parts .= '--' . $related_boundary . "\r\n"
                . 'Content-Type: ' . $content_type . "\r\n"
                . "Content-Transfer-Encoding: base64\r\n"
                . 'Content-ID: <' . $content_id . ">\r\n"
                . "Content-Disposition: inline\r\n\r\n"
                . chunk_split(base64_encode($contents)) . "\r\n";
            continue;
        }

        $attachment_parts .= '--' . $boundary . "\r\n"
            . 'Content-Type: ' . $content_type . '; name="' . $filename . "\"\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . 'Content-Disposition: attachment; filename="' . $filename . "\"\r\n\r\n"
            . chunk_split(base64_encode($contents)) . "\r\n";
    }

    if ($inline_parts !== '') {
        $body = '--' . $boundary . "\r\n"
            . 'Content-Type: multipart/related; boundary="' . $related_boundary . "\"\r\n\r\n"
            . '--' . $related_boundary . "\r\n"
            . $alternative_part
            . $inline_parts
            . '--' . $related_boundary . "--\r\n";
    } else {
        $body = '--' . $boundary . "\r\n" . $alternative_part;
    }

    return implode("\r\n", $headers) . "\r\n\r\n"
        . $body
        . $attachment_parts
        . '--' . $boundary . "--\r\n";
}

function smtp_send_mail(array $config, $from_email, $from_name, $to_email, $subject, $plain_message, $html_message, array $attachments = [])
{
    $host = trim((string)($config['host'] ?? ''));
    $port = (int)($config['port'] ?? 0);
    $encryption = strtolower(trim((string)($config['encryption'] ?? 'tls')));
    $username = trim((string)($config['username'] ?? ''));
    $password = (string)($config['password'] ?? '');
    $timeout = max(5, (int)($config['timeout'] ?? 20));

    if ($host === '' || $port <= 0 || $username === '' || $password === '') {
        throw new InvalidArgumentException('SMTP configuration is incomplete.');
    }
    if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
        throw new InvalidArgumentException('SMTP encryption setting is invalid.');
    }

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'peer_name' => $host
        ]
    ]);
    $transport = $encryption === 'ssl' ? 'ssl' : 'tcp';
    $remote = $transport . '://' . $host . ':' . $port;
    $socket = @stream_socket_client($remote, $error_number, $error_message, $timeout, STREAM_CLIENT_CONNECT, $context);

    if (!$socket) {
        throw new RuntimeException('Unable to connect to SMTP server.');
    }

    stream_set_timeout($socket, $timeout);
    $client_name = preg_replace('/[^a-z0-9.-]/i', '', gethostname() ?: 'localhost') ?: 'localhost';

    try {
        smtp_expect($socket, [220]);
        smtp_command($socket, 'EHLO ' . $client_name, [250]);

        if ($encryption === 'tls') {
            smtp_command($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Unable to start encrypted SMTP connection.');
            }
            smtp_command($socket, 'EHLO ' . $client_name, [250]);
        }

        smtp_command($socket, 'AUTH LOGIN', [334]);
        smtp_command($socket, base64_encode($username), [334]);
        smtp_command($socket, base64_encode($password), [235]);
        smtp_command($socket, 'MAIL FROM:<' . $from_email . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . $to_email . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);

        $message = smtp_build_message($from_email, $from_name, $to_email, $subject, $plain_message, $html_message, $attachments);
        $message = preg_replace('/(?m)^\./', '..', $message);
        smtp_write_all($socket, $message . "\r\n.\r\n");
        smtp_expect($socket, [250]);
        smtp_command($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
}
