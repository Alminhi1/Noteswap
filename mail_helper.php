<?php
require_once 'mail_config.php';

function noteswap_smtp_read($socket) {
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    return $response;
}

function noteswap_smtp_command($socket, $command, $expected_codes) {
    fwrite($socket, $command . "\r\n");
    $response = noteswap_smtp_read($socket);
    $code = intval(substr($response, 0, 3));
    if (!in_array($code, (array)$expected_codes, true)) {
        throw new Exception(trim($response));
    }
    return $response;
}

function noteswap_dot_stuff($body) {
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $lines = explode("\n", $body);
    foreach ($lines as &$line) {
        if (isset($line[0]) && $line[0] === '.') {
            $line = '.' . $line;
        }
    }
    unset($line);
    return implode("\r\n", $lines);
}

function noteswap_send_smtp_mail($to, $subject, $body) {
    $host = SMTP_HOST;
    $port = SMTP_PORT;
    $encryption = strtolower(SMTP_ENCRYPTION);
    $from_email = SMTP_FROM_EMAIL;
    $from_name = SMTP_FROM_NAME;
    $server = $encryption === 'ssl' ? 'ssl://' . $host . ':' . $port : $host . ':' . $port;

    $socket = stream_socket_client($server, $errno, $errstr, 20);
    if (!$socket) {
        throw new Exception($errstr ?: 'Could not connect to SMTP server.');
    }

    try {
        $greeting = noteswap_smtp_read($socket);
        if (intval(substr($greeting, 0, 3)) !== 220) {
            throw new Exception(trim($greeting));
        }

        $localhost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        noteswap_smtp_command($socket, 'EHLO ' . $localhost, [250]);

        if ($encryption === 'tls') {
            noteswap_smtp_command($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception('Could not start TLS encryption.');
            }
            noteswap_smtp_command($socket, 'EHLO ' . $localhost, [250]);
        }

        if (SMTP_USERNAME !== '') {
            noteswap_smtp_command($socket, 'AUTH LOGIN', [334]);
            noteswap_smtp_command($socket, base64_encode(SMTP_USERNAME), [334]);
            noteswap_smtp_command($socket, base64_encode(SMTP_PASSWORD), [235]);
        }

        noteswap_smtp_command($socket, 'MAIL FROM:<' . $from_email . '>', [250]);
        noteswap_smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        noteswap_smtp_command($socket, 'DATA', [354]);

        $headers = [];
        $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
        $headers[] = 'To: <' . $to . '>';
        $headers[] = 'Subject: ' . $subject;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';

        fwrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . noteswap_dot_stuff($body) . "\r\n.\r\n");
        $response = noteswap_smtp_read($socket);
        if (intval(substr($response, 0, 3)) !== 250) {
            throw new Exception(trim($response));
        }

        noteswap_smtp_command($socket, 'QUIT', [221]);
        fclose($socket);
        return true;
    } catch (Exception $e) {
        fclose($socket);
        throw $e;
    }
}

function noteswap_send_reset_email($to, $subject, $body, &$error = '') {
    if (defined('SMTP_HOST') && SMTP_HOST !== '') {
        try {
            return noteswap_send_smtp_mail($to, $subject, $body);
        } catch (Exception $e) {
            $error = $e->getMessage();
            return false;
        }
    }

    $from_email = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'no-reply@noteswap.local';
    $from_name = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'NoteSwap';
    $headers = "From: " . $from_name . " <" . $from_email . ">\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";

    if (@mail($to, $subject, $body, $headers)) {
        return true;
    }

    $error = 'PHP mail() is not configured.';
    return false;
}
