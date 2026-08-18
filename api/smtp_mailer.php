<?php
declare(strict_types=1);

/* Dependency-free authenticated SMTP sender for Hostinger shared hosting.
   Supports SMTP over SSL on 465 and STARTTLS on 587. */
function ign_smtp_send(array $cfg, string $to, string $subject, string $body, ?string &$error = null): bool {
    $host = (string)($cfg['host'] ?? 'smtp.hostinger.com');
    $port = (int)($cfg['port'] ?? 465);
    $user = (string)($cfg['user'] ?? '');
    $pass = (string)($cfg['pass'] ?? '');
    $from = (string)($cfg['from'] ?? $user);
    $fromName = (string)($cfg['from_name'] ?? 'InvoiceGeneratorNow');
    if ($user === '' || $pass === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $error = 'SMTP credentials or recipient are missing.';
        return false;
    }

    $timeout = 20;
    $ssl = $port === 465;
    $transport = $ssl ? "ssl://{$host}" : $host;
    $context = stream_context_create(['ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'allow_self_signed' => false,
        'SNI_enabled' => true,
        'peer_name' => $host,
    ]]);
    $socket = @stream_socket_client("{$transport}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        $error = "SMTP connection failed: {$errstr} ({$errno})";
        return false;
    }
    stream_set_timeout($socket, $timeout);

    $read = static function () use ($socket): string {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $send = static function (string $command) use ($socket): void {
        fwrite($socket, $command . "\r\n");
    };
    $expect = static function (string $response, array $codes) use (&$error): bool {
        $code = substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            $error = 'Unexpected SMTP response: ' . trim($response);
            return false;
        }
        return true;
    };

    $response = $read();
    if (!$expect($response, ['220'])) { fclose($socket); return false; }
    $send('EHLO invoicegeneratornow.com');
    $response = $read();
    if (!$expect($response, ['250'])) { fclose($socket); return false; }

    if (!$ssl) {
        $send('STARTTLS');
        $response = $read();
        if (!$expect($response, ['220'])) { fclose($socket); return false; }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            $error = 'SMTP TLS negotiation failed.';
            fclose($socket);
            return false;
        }
        $send('EHLO invoicegeneratornow.com');
        $response = $read();
        if (!$expect($response, ['250'])) { fclose($socket); return false; }
    }

    $send('AUTH LOGIN');
    if (!$expect($read(), ['334'])) { fclose($socket); return false; }
    $send(base64_encode($user));
    if (!$expect($read(), ['334'])) { fclose($socket); return false; }
    $send(base64_encode($pass));
    if (!$expect($read(), ['235'])) { fclose($socket); return false; }

    $send("MAIL FROM:<{$from}>");
    if (!$expect($read(), ['250'])) { fclose($socket); return false; }
    $send("RCPT TO:<{$to}>");
    if (!$expect($read(), ['250', '251'])) { fclose($socket); return false; }
    $send('DATA');
    if (!$expect($read(), ['354'])) { fclose($socket); return false; }

    $safeSubject = str_replace(["\r", "\n"], '', $subject);
    $messageId = sprintf('<%s@invoicegeneratornow.com>', bin2hex(random_bytes(12)));
    $headers = 'From: ' . mb_encode_mimeheader($fromName) . " <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "To: <{$to}>\r\n";
    $headers .= 'Subject: ' . mb_encode_mimeheader($safeSubject) . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";
    $headers .= 'Date: ' . date('r') . "\r\n";
    $headers .= "Message-ID: {$messageId}\r\n";
    $headers .= "X-Auto-Response-Suppress: All\r\n";
    $bodyOut = preg_replace('/^\./m', '..', str_replace("\n", "\r\n", $body));
    $send($headers . "\r\n" . $bodyOut . "\r\n.");
    if (!$expect($read(), ['250'])) { fclose($socket); return false; }
    $send('QUIT');
    fclose($socket);
    return true;
}
