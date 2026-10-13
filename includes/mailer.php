<?php
// Client SMTP minimale nativo (SMTPS implicito su 465, AUTH LOGIN).
// Nessuna dipendenza esterna. Ritorna true se il server accetta il messaggio.
function sendMail($to, $subject, $htmlBody) {
    $host   = getenv('SMTP_HOST');
    $port   = (int)(getenv('SMTP_PORT') ?: 465);
    $user   = getenv('SMTP_USER');
    $pass   = getenv('SMTP_PASS');
    $from   = getenv('SMTP_FROM') ?: $user;
    $secure = getenv('SMTP_SECURE') ?: 'ssl';
    if (!$host || !$user || !$pass) { error_log('sendMail: SMTP non configurato'); return false; }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $endpoint = ($secure === 'ssl') ? "ssl://{$host}:{$port}" : "{$host}:{$port}";
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = @stream_socket_client($endpoint, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { error_log("sendMail connect fail: $errstr ($errno)"); return false; }
    stream_set_timeout($fp, 15);

    $read = function () use ($fp) {
        $data = '';
        while (($line = fgets($fp, 600)) !== false) {
            $data .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break; // ultima riga di una risposta multilinea
        }
        return $data;
    };
    $ok  = function ($resp, $code) { return substr($resp, 0, 3) === (string)$code; };
    $cmd = function ($c) use ($fp, $read) { fwrite($fp, $c . "\r\n"); return $read(); };

    try {
        if (!$ok($read(), 220))                          throw new Exception('greet');
        if (!$ok($cmd('EHLO fuelfinder.fmenegazzi.it'), 250)) throw new Exception('ehlo');
        if (!$ok($cmd('AUTH LOGIN'), 334))               throw new Exception('auth');
        if (!$ok($cmd(base64_encode($user)), 334))       throw new Exception('user');
        if (!$ok($cmd(base64_encode($pass)), 235))       throw new Exception('pass');
        if (!$ok($cmd("MAIL FROM:<{$from}>"), 250))      throw new Exception('from');
        if (!$ok($cmd("RCPT TO:<{$to}>"), 250))          throw new Exception('rcpt');
        if (!$ok($cmd('DATA'), 354))                     throw new Exception('data');

        $headers = implode("\r\n", [
            'From: FuelFinder <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@fmenegazzi.it>',
            'Date: ' . date('r'),
        ]);
        // Body in base64 -> nessun problema di dot-stuffing (le righe base64 non iniziano con '.')
        $payload = $headers . "\r\n\r\n" . chunk_split(base64_encode($htmlBody)) . "\r\n.";
        if (!$ok($cmd($payload), 250))                   throw new Exception('send');
        $cmd('QUIT');
        fclose($fp);
        return true;
    } catch (Throwable $e) {
        error_log('sendMail step fail: ' . $e->getMessage());
        @fclose($fp);
        return false;
    }
}

// Template HTML semplice e coerente col brand.
function mailTemplate($title, $bodyHtml) {
    return '<div style="font-family:Inter,Arial,sans-serif;background:#0b1220;padding:24px;color:#f1f5f9">'
         . '<div style="max-width:480px;margin:0 auto;background:#131c2e;border:1px solid #2c3a50;border-radius:14px;padding:28px">'
         . '<div style="font-size:20px;font-weight:700;margin-bottom:16px">Fuel<span style="color:#10b981">Finder</span></div>'
         . '<h2 style="font-size:17px;margin:0 0 12px">' . htmlspecialchars($title) . '</h2>'
         . '<div style="font-size:14px;line-height:1.6;color:#d4d4e4">' . $bodyHtml . '</div>'
         . '<p style="font-size:11px;color:#94a3b8;margin-top:24px">FuelFinder · se non hai richiesto questa email, ignorala.</p>'
         . '</div></div>';
}
