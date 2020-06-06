<?php
error_reporting(E_ALL);

/* Allow the script to hang around waiting for connections. */
set_time_limit(0);

/* Turn on implicit output flushing so we see what we're getting
 * as it comes in. */
ob_implicit_flush();

$serverHello = 'fakeSMTP ESMTP PHP Mail Server Ready';
$header = true;
$address = 'smtp.goodsane.com'; //smtp.goodsane.com
$port = 6013;
$ssl = openssl_get_cert_locations();
$connect = false;
$users = [
    'admin@goodsane.com' => 'goodsaneadmin',
];

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
    echo "Failed to connect : $address\n";
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

        $encrypted = false;
        $receivingData = false;
        $hasValidFrom = false;
        $hasValidTo = false;
        $login = false;
        $mail = '';
        $from = '';
        $to = [];

        // Start TCP handshake
        fwrite($client, '220 '.$serverHello."\n");
        // Retrieve IP from connection
        $ip = explode(':', stream_socket_get_name($client, true))[0];

        echo ("\r\n".$client.json_encode($client))."\n";

        // If client connection exists
        if($client) {
            // When data received
            while ($client && $data = fgets($client)) {
                // Replace '\r\n' line ending symbol
                $data = preg_replace('@\r\n@', "\n", $data);
                if(!$receivingData) {
                    echo "\r\nMessage: $data"."\n";
                }
                /////////////////////////////////////////
                // Message analysis and response cases //
                /////////////////////////////////////////

                if (!$receivingData && preg_match('/^MAIL FROM:\s?<(.*)>/i', $data, $match)) {
                    // Sender address validation
                    if (preg_match('/(.*)@\[.*\]/i', $match[1]) || $match[1] != '' || $this->validateEmail($match[1])) {
                        $from = $match[1];
                        fwrite($client, '250 2.1.0 Ok'."\n");
                        echo "\r\n".'S: 250 2.1.0 Ok From '."$from\n";
                        $hasValidFrom = true;
                    } else {
                        fwrite($client, '551 5.1.7 Bad sender address syntax'."\n");
                        echo "\r\n".'S: 551 5.1.7 Bad sender address syntax'."\n";
                    }
                    // Authentication
                } elseif (preg_match('/AUTH\s(.*)/i', $data, $match)) {
                    if(preg_match('/LOGIN/i', $match[1])){
                        echo "\r\n".'S: Login Start'."\n";
                        fwrite($client, '334 VXNlcm5hbWU6'."\n");
                        echo "\r\n".'S: 334 VXNlcm5hbWU6'."\n";
                        $data = fgets($client);
                        if(isset($users[base64_decode($data)])) {
                            $user = base64_decode($data);
                            echo "\r\n$: $data :: ".base64_decode($data);
                            fwrite($client, '334 LIRdf2pekwW3'."\n");
                            echo "\r\n".'S: 334 LIRdf2pekwW3'."\n";
                            $data = fgets($client);
                            $data = trim($data);
                            if(strcmp($data,base64_encode($users[$user]))==0){
                                echo "\r\n$$ VALID\n";
                                fwrite($client, "235 Authentication successful\n");
                                echo "\r\nS: 235 Authentication successful\n";
                                $login = true;
                            } else {
                                fwrite($client, "535 Authentication failed\n");
                                echo "\r\nS: 535 Authentication failed\n";
                                fwrite($client, '221 2.0.0 Bye '.$ip."\n");
                                echo "\r\n".'S: 221 2.0.0 Bye '.$ip."\n";
                                fclose($client);
                                $client = false;
                                $connect = false;
                                $encrypted = false;
                            }
                        } else {
                            fwrite($client, "535 Authentication failed\n");
                            echo "\r\nS: 535 Authentication failed\n";
                            fwrite($client, '221 2.0.0 Bye '.$ip."\n");
                            echo "\r\n".'S: 221 2.0.0 Bye '.$ip."\n";
                            fclose($client);
                            $client = false;
                            $connect = false;
                            $encrypted = false;
                        }
                        
                    }
                    // STARTTLS handshake
                } elseif (!$receivingData && preg_match('/STARTTLS/', $data)) {
                    fwrite($client, '220 GO AHEAD'."\n");
                    echo "\r\n".'S: 220 GO AHEAD'."\n";
                    
                    // Enable encrypted connection
                    stream_socket_enable_crypto($client, true, STREAM_CRYPTO_METHOD_TLSv1_2_SERVER);
                    $encrypted = true;
                    // Recipient address validation
                } elseif (!$receivingData && preg_match('/^RCPT TO:\s?<(.*)>/i', $data, $match)) {
                    if (!$hasValidFrom) {
                        fwrite($client, '503 5.5.1 Error: need MAIL command'."\n");
                        echo "\r\n".'S: 503 5.5.1 Error: need MAIL command'."\n";
                    } else {
                        if (preg_match('/postmaster@\[.*\]/i', $match[1]) || validateEmail($match[1])) {
                            array_push($to, $match[1]);
                            fwrite($client, '250 2.1.5 Ok'."\n");
                            echo "\r\n".'S: 250 2.1.5 Ok To '.json_encode($to)."\n";
                            // fwrite($client, '354 2.1.5 Ok'."\n");
                            // echo "\r\n".'S: 354 2.1.5 Ok To '.json_encode($to)."\n";
                            $hasValidTo = true;
                        } else {
                            fwrite($client, '501 5.1.3 Bad recipient address syntax '.$match[1]."\n");
                            echo "\r\n".'S: 501 5.1.3 Bad recipient address syntax '.$match[1]."\n";
                        }
                    }
                    // Handshake reset
                } elseif (!$receivingData && preg_match('/^RSET$/i', trim($data))) {
                    fwrite($client, '250 2.0.0 Ok'."\n");
                    echo "\r\n".'S: 250 2.0.0 Ok'."\n";
                    $hasValidFrom = false;
                    $hasValidTo = false;
                    $login = false;
                } elseif (!$receivingData && preg_match('/^NOOP$/i', trim($data))) {
                    fwrite($client, '250 2.0.0 Ok'."\n");
                    echo "\r\n".'S: 250 2.0.0 Ok'."\n";
                } elseif (!$receivingData && preg_match('/^VRFY (.*)/i', trim($data), $match)) {
                    fwrite($client, '250 2.0.0 '.$match[1]."\n");
                    echo "\r\n".'S: 250 2.0.0 Ok '.$match[1]."\n";
                    // Start sending DATA
                } elseif (!$receivingData && preg_match('/^DATA/i', trim($data))) {
                    if (!$hasValidTo) {
                        fwrite($client, '503 5.5.1 Error: need RCPT command'."\n");
                        echo "\r\n".'S: 503 5.5.1 Error: need RCPT command'."\n";
                    } else {
                        fwrite($client, '354 Ok Send data ending with <CRLF>.<CRLF>'."\n");
                        echo "\r\n".'S: 354 Ok Send data ending with <CRLF>.<CRLF>'."\n";
                        // fwrite($client, '250 Ok Send data ending with <CRLF>.<CRLF>'."\n");
                        // echo "\r\n".'S: 250 Ok Send data ending with <CRLF>.<CRLF>'."\n";
                        $receivingData = true;
                    }
                    // Handshake HELO after encrypted
                } elseif (!$receivingData && preg_match('/^(HELO|EHLO)/i', $data) && $encrypted) {
                    // HELO with features supported
                    fwrite($client, "250-HELO from BrightCMS Fake SMTP Server, [$ip]\r\n250-SIZE 35882577\r\n250-AUTH LOGIN \r\n250-ENHANCEDSTATUSCODES\r\n250 SMTPUTF8\r\n");
                    // fwrite($client, "250-HELO from BrightCMS Fake SMTP Server, [$ip]\r\n250-SIZE 35882577\r\n250-8BITMIME\r\n250-AUTH LOGIN PLAIN XOAUTH2 PLAIN-CLIENTTOKEN OAUTHBEARER XOAUTH\r\n250-ENHANCEDSTATUSCODES\r\n250-PIPELINING\r\n250-CHUNKING\r\n250 SMTPUTF8\r\n");

                    echo "\r\n".'S: 250 AUTH LOGIN PLAIN CRAM-MD5'."\n";
                    // Handshake HELO
                } elseif (!$receivingData && preg_match('/^(HELO|EHLO)/i', $data)) {
                    fwrite($client, '250 HELO '.$ip.' AUTH LOGIN PLAIN CRAM-MD5 STARTTLS HELP '."\n");
                    echo "\r\n".'S: 250 HELO '.$ip."\n";
                    // End Handshake
                } elseif (!$receivingData && preg_match('/^QUIT/i', trim($data))) {
                    break;
                    // Invalid command received
                } elseif (!$receivingData) {
                    fwrite($client, '502 5.5.2 Error: command not recognized'."\n");
                    echo "\r\n".'S: 502 5.5.2 Error: command not recognized'."\n";
                    // End of DATA receiving
                } elseif ($receivingData && $data == ".\n") {
                    /* Email Received, now let's look at it */
                    $receivingData = false;
                    echo "\r\nMails: ".json_encode($mail)."\n";
                    fwrite($client, '250 2.0.0 Ok: queued'."\n");
                    echo "\r\n".'S: 250 2.0.0 Ok: queued'."\n";
                    $splitmail = explode("\n\n", $mail, 2);
                    if (count($splitmail) == 2) {
                        $rawheaders = $splitmail[0];
                        echo "\r\n"."rawHeaders: ".json_encode($rawheaders)."\n";
                        $body = $splitmail[1];
                        echo "\r\n"."body: ".json_encode($body)."\n";
                        $headers = preg_replace("/ \s+/", ' ', preg_replace("/\n\s/", ' ', $rawheaders));
                        echo "\r\n"."headers: ".json_encode($headers)."\n";
                        $headerlines = explode("\n", $headers);
                        echo "\r\n"."headerlines: ".json_encode($headerlines)."\n";
                        for ($i=0; $i<count($headerlines); $i++) {
                            if (preg_match('/^Subject: (.*)/i', $headerlines[$i], $matches)) {
                                $subject = trim($matches[1]);
                            }
                            if (preg_match('/^From: (.*) <.*>/i', $headerlines[$i], $matches)) {
                                $senderName = trim($matches[1]);
                            }
                        }
                        echo "\r\n"."subject: ".$subject."\n";

                        $start = strpos($body, "<html>");
                        $end = strpos($body, "</html>");
                        $length = $end-$start+strlen("</html>");
                        $content = substr($body,$start, $length);
                        echo "\r\n S:: ".$content;
                        
                    //     if (admin_email::where('name', $senderName)->count() < 1) {
                    //       $sender = admin_email::create([
                    //         'name' => $senderName,
                    //         'email' => $from
                    //       ]);
                    //     } else {
                    //       $sid = admin_email::where('name', $senderName)->pluck('id');
                    //       $sender = admin_email::find($sid);
                    //     }

                    //     $temp = [
                    //         'sender_id' => $sender->id,
                    //         'subject' => $subject,
                    //         'content' => $content,
                    //         'status' => 1
                    //   ];
                    //     $newMail = email_content::create($temp);

                    } 
                    else {
                        $body = $splitmail[0];
                        echo "\r\n"."body:: ".json_encode($body)."\n";

                    }
                    // Close connection
                    fwrite($client, '221 2.0.0 Bye '.$ip."\n");
                    echo "\r\n".'S: 221 2.0.0 Bye '.$ip."\n";
                    // fclose($client);
                    // echo "\r\n".'Conection Closed'.$ip;
                    $connect = false;
                    $client = false;
                    $encrypted = false;
                    $login = false;
                    set_time_limit(5); // Just run the exit to prevent open threads / abuse
                    break;
                } elseif ($receivingData) {
                    $mail .= quoted_printable_decode($data);
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