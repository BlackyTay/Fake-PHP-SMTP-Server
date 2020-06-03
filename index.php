<?php
error_reporting(E_ALL);

/* Allow the script to hang around waiting for connections. */
set_time_limit(0);

/* Turn on implicit output flushing so we see what we're getting
 * as it comes in. */
ob_implicit_flush();

$serverHello = 'fakeSMTP ESMTP PHP Mail Server Ready';
$header = true;
$address = 'smtp.goodsane.com';
$port = 6006;
$ssl = openssl_get_cert_locations();
$connect = false;

// Config SSL certificate realated setting
$stream_context = stream_context_create([
     'ssl' => [
        'local_cert'        => '/home/mailblast/ssl/Cert.crt',
        'local_pk'          => '/home/mailblast/ssl/Private.key',
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'capture_peer_cert' => false,
        'allow_self_signed' => true,
    ]
]);

// Listen to connection to address and port
$server = stream_socket_server("tcp://$address:$port", $errno, $errMsg, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $stream_context);
// Turn off secure encrypted connection
stream_socket_enable_crypto($server, false);
// If cannot connect
if($server === false) {
    echo "Failed to connect : $address";
}

while(true) {

    // When no on going connection, accept connection
    while(!$connect) {
        if($client = @stream_socket_accept($server))
            $connect = true;
    }

    // Start connection
    while($connect) {
        // $client = @stream_socket_accept($server);

        $receivingData = false;
        $hasValidFrom = false;
        $hasValidTo = false;
        $mail = '';
        $from = '';
        $to = [];

        // Start TCP handshake
        fwrite($client, '220 '.$serverHello."\n");
        // Retrieve IP from connection
        $ip = explode(':', stream_socket_get_name($client, true))[0];

        echo ("\r\n".$client.json_encode($client));

        // If client connection exists
        if($client) {
            // When data received
            while ($data = fgets($client)) {
                // Replace '\r\n' line ending symbol
                $data = preg_replace('@\r\n@', "\n", $data);
                if(!$receivingData) {
                    echo "\r\nMessage: $data";
                }

                /////////////////////////////////////////
                // Message analysis and response cases //
                /////////////////////////////////////////

                if (!$receivingData && preg_match('/^MAIL FROM:\s?<(.*)>/i', $data, $match)) {
                    // Sender address validation
                    if (preg_match('/(.*)@\[.*\]/i', $match[1]) || $match[1] != '' || $this->validateEmail($match[1])) {
                        $from = $match[1];
                        fwrite($client, '250 2.1.0 Ok'."\n");
                        echo "\r\n".'S: 250 2.1.0 Ok';
                        $hasValidFrom = true;
                    } else {
                        fwrite($client, '551 5.1.7 Bad sender address syntax'."\n");
                        echo "\r\n".'S: 551 5.1.7 Bad sender address syntax';
                    }
                    // STARTTLS handshake
                } elseif (!$receivingData && preg_match('/STARTTLS/', $data)) {
                    fwrite($client, '220 GO AHEAD'."\n");
                    echo "\r\n".'S: 220 GO AHEAD';
                    
                    // Enable encrypted connection
                    stream_socket_enable_crypto($client, true, STREAM_CRYPTO_METHOD_TLSv1_2_SERVER);
                    // Recipient address validation
                } elseif (!$receivingData && preg_match('/^RCPT TO:\s?<(.*)>/i', $data, $match)) {
                    if (!$hasValidFrom) {
                        fwrite($client, '503 5.5.1 Error: need MAIL command'."\n");
                        echo "\r\n".'S: 503 5.5.1 Error: need MAIL command';
                    } else {
                        if (preg_match('/postmaster@\[.*\]/i', $match[1]) || validateEmail($match[1])) {
                            array_push($to, $match[1]);
                            fwrite($client, '250 2.1.5 Ok'."\n");
                            echo "\r\n".'S: 250 2.1.5 Ok';
                            $hasValidTo = true;
                        } else {
                            fwrite($client, '501 5.1.3 Bad recipient address syntax '.$match[1]."\n");
                            echo "\r\n".'S: 501 5.1.3 Bad recipient address syntax '.$match[1];
                        }
                    }
                    // Handshake reset
                } elseif (!$receivingData && preg_match('/^RSET$/i', trim($data))) {
                    fwrite($client, '250 2.0.0 Ok'."\n");
                    echo "\r\n".'S: 250 2.0.0 Ok';
                    $hasValidFrom = false;
                    $hasValidTo = false;
                } elseif (!$receivingData && preg_match('/^NOOP$/i', trim($data))) {
                    fwrite($client, '250 2.0.0 Ok'."\n");
                    echo "\r\n".'S: 250 2.0.0 Ok';
                } elseif (!$receivingData && preg_match('/^VRFY (.*)/i', trim($data), $match)) {
                    fwrite($client, '250 2.0.0 '.$match[1]."\n");
                    echo "\r\n".'S: 250 2.0.0 Ok'.$match[1];
                    // Start sending DATA
                } elseif (!$receivingData && preg_match('/^DATA/i', trim($data))) {
                    if (!$hasValidTo) {
                        fwrite($client, '503 5.5.1 Error: need RCPT command'."\n");
                        echo "\r\n".'S: 503 5.5.1 Error: need RCPT command';
                    } else {
                        fwrite($client, '354 Ok Send data ending with <CRLF>.<CRLF>'."\n");
                        echo "\r\n".'S: 354 Ok Send data ending with <CRLF>.<CRLF>';
                        $receivingData = true;
                    }
                    // Handshake HELO
                } elseif (!$receivingData && preg_match('/^(HELO|EHLO)/i', $data)) {
                    fwrite($client, '250 HELO '.$ip.' STARTTLS AUTH '."\n");
                    echo "\r\n".'S: 250 HELO '.$ip;
                    // End Handshake
                } elseif (!$receivingData && preg_match('/^QUIT/i', trim($data))) {
                    break;
                    // Invalid command received
                } elseif (!$receivingData) {
                    fwrite($client, '502 5.5.2 Error: command not recognized'."\n");
                    echo "\r\n".'S: 502 5.5.2 Error: command not recognized';
                    // End of DATA receiving
                } elseif ($receivingData && $data == ".\n") {
                    /* Email Received, now let's look at it */
                    $receivingData = false;
                    echo "\r\nMails: ".json_encode($mail);
                    fwrite($client, '250 2.0.0 Ok: queued'."\n");
                    echo "\r\n".'S: 250 2.0.0 Ok: queued';
                    $splitmail = explode("\n\n", $mail, 2);
                    if (count($splitmail) == 2) {
                        $rawheaders = $splitmail[0];
                        echo "\r\n"."rawHeaders: ".json_encode($rawheaders);
                        $body = $splitmail[1];
                        echo "\r\n"."body: ".json_encode($body);
                        $headers = preg_replace("/ \s+/", ' ', preg_replace("/\n\s/", ' ', $rawheaders));
                        echo "\r\n"."headers: ".json_encode($headers);
                        $headerlines = explode("\n", $headers);
                        echo "\r\n"."headerlines: ".json_encode($headerlines);
                        for ($i=0; $i<count($headerlines); $i++) {
                            if (preg_match('/^Subject: (.*)/i', $headerlines[$i], $matches)) {
                                $subject = trim($matches[1]);
                            }
                        }
                        echo "\r\n"."subject: ".json_encode($subject);

                    } else {
                        $body = $splitmail[0];
                        echo "\r\n"."body:: ".json_encode($body);

                    }
                    // Close connection
                    fwrite($client, '221 2.0.0 Bye '.$ip."\n");
                    echo "\r\n".'S: 221 2.0.0 Bye '.$ip;
                    // fclose($client);
                    // echo "\r\n".'Conection Closed'.$ip;
                    $connect = false;
                    $client = false;
                    set_time_limit(5); // Just run the exit to prevent open threads / abuse
                    break;
                } elseif ($receivingData) {
                    $mail .= $data;
                }

            }
        }
    }
}

function validateEmail($email)
    {
        return preg_match('/^[_a-z0-9-+]+(\.[_a-z0-9-+]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/', strtolower($email));
    }
?>