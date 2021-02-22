<?php
error_reporting(E_ALL);
date_default_timezone_set('Asia/Kuching');

/* Allow the script to hang around waiting for connections. */
set_time_limit(0);

/* Turn on implicit output flushing so we see what we're getting
 * as it comes in. */
ob_implicit_flush();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
include '../PHPMailer/src/Exception.php';
include '../PHPMailer/src/PHPMailer.php';
include '../PHPMailer/src/SMTP.php';

$sqlServer = "mysql.goodsane.com";
$sqlDBname = "mailblasting";
$sqlUsername = "mailblasting";
$sqlPassword = "1c8a123d5f5e4f93f717c299250541c";

// Create Sql Connection
$conn = new mysqli($sqlServer, $sqlUsername, $sqlPassword, $sqlDBname);

//Check Sql Connection
if(mysqli_connect_error()){
    die("Database connection failed: ".mysqli_connect_error());
}
echo "\r\nSql Connected Successfully\n";

// mysqli_close($conn);
// echo "\r\nSql Connection Closed\n";

$serverHello = 'fakeSMTP ESMTP PHP Mail Server Ready';
$header = true;
$address = getAddress($conn); //smtp.goodsane.com
$port = getPort($conn);
$ssl = openssl_get_cert_locations();
$connect = false;
$users = getSmtpUser($conn);

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
        $test = fwrite($client, '220 '.$serverHello."\n");
        if($test == 0){
            fclose($client);
            $client = null;
            $connect = false;
            break;
        }
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

                if (!$receivingData && preg_match('/^MAIL FROM:\s?<(.*)>/i', $data, $match) && $login) {
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
                        $data = fgets($client);
                        echo "\r\n".'S: 334 VXNlcm5hbWU6'."\n";
                        if(isset($users[base64_decode($data)])) {
                            $user = base64_decode($data);
                            $smtp_user_id = getSmtpUserId($conn, $user);
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
                } elseif (!$receivingData && preg_match('/^RCPT TO:\s?<(.*)>/i', $data, $match) && $login) {
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
                } elseif ($receivingData && $data == ".\n" && $login) {
                    /* Email Received, now let's look at it */
                    $receivingData = false;
                    // echo "\r\nMails: ".json_encode($mail)."\n";
                    fwrite($client, '250 2.0.0 Ok: queued'."\n");
                    echo "\r\n".'S: 250 2.0.0 Ok: queued'."\n";
                    $splitmail = explode("\n\n", $mail, 2);
                    if (count($splitmail) == 2) {
                        $rawheaders = $splitmail[0];
                        // echo "\r\n"."rawHeaders: ".json_encode($rawheaders)."\n";
                        $body = $splitmail[1];
                        // echo "\r\n"."body: ".json_encode($body)."\n";
                        $headers = preg_replace("/ \s+/", ' ', preg_replace("/\n\s/", ' ', $rawheaders));
                        // echo "\r\n"."headers: ".json_encode($headers)."\n";
                        $headerlines = explode("\n", $headers);
                        // echo "\r\n"."headerlines: ".json_encode($headerlines)."\n";
                        for ($i=0; $i<count($headerlines); $i++) {
                            if (preg_match('/^Subject: (.*)/i', $headerlines[$i], $matches)) {
                                $subject = trim($matches[1]);
                            }
                            if (preg_match('/^From: (.*) <.*>/i', $headerlines[$i], $matches)) {
                                $senderName = trim($matches[1]);
                            }
                        }
                        // echo "\r\n"."subject: ".$subject."\n";

                        $start = strpos($body, "<html");
                        $end = strpos($body, "</html>");
                        $length = $end-$start+strlen("</html>");
                        $content = substr($body,$start, $length);
                        // echo "\r\n S:: ".$content;
                        
                        // Database Operations
                        if (!$sender_id = checkAdminEmail($conn, $senderName)){
                            $sender_id = insertAdminEmail($conn, $senderName, $from);
                        }

                        $email_content_id = insertEmailContent($conn, $sender_id, $subject, $content);

                        $smtp_account = getSmtpAccount($conn);
                        foreach($to as $recipient) {
                            if (!$user_email_id = checkUserEmail($conn, $recipient)){
                                $user_email_id = insertUserEmail($conn, "Unnamed User", $recipient);
                                insertUserTag($conn, $user_email_id, $user);
                            }

                            $outbox_email_id = insertOutboxEmail($conn, $sender_id, $user_email_id, $email_content_id, $smtp_user_id);
                            echo "\r\nOutboxId : $outbox_email_id\r\n";

                            $status = 0;
                            foreach($smtp_account as $account) {
                                if(($status=sendMail($account, $senderName, $from, $to[0], $subject, $content))==1) {
                                    break;
                                }
                            }

                            if ($status == 1) {
                                updateOutboxEmailStatus($conn, $outbox_email_id, $status);
                            }
                        }

                    } 
                    else {
                        $body = $splitmail[0];
                        echo "\r\n"."body:: ".json_encode($body)."\n";

                    }
                    // Close connection
                    fwrite($client, '221 2.0.0 Bye '.$ip."\n");
                    echo "\r\n".'S: 221 2.0.0 Bye '.$ip."\n";
                    fclose($client);
                    // echo "\r\n".'Conection Closed'.$ip;
                    $connect = false;
                    $client = false;
                    $encrypted = false;
                    $login = false;
                    set_time_limit(10); // Just run the exit to prevent open threads / abuse
                    break;
                } elseif ($receivingData && $login) {
                    $mail .= quoted_printable_decode($data);
                } elseif (($receivingData && !$login)||(!$receivingData && preg_match('/^MAIL FROM:\s?<(.*)>/i', $data, $match) && !$login) || (!$receivingData && preg_match('/^RCPT TO:\s?<(.*)>/i', $data, $match) && !$login)) {
                    fwrite($client, '550 Authentication required '."\n");
                    echo "\r\n".'S: 550 Authentication required '."\n";
                }

            }
        }
    }
}

function validateEmail($email)
{
    return preg_match('/^[_a-z0-9-+]+(\.[_a-z0-9-+]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/', strtolower($email));
}

function getPort($conn) {
    $sql = "SELECT port FROM smtp_config";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $port = $row['port'];
    }
    return $port;
}

function getAddress($conn) {
    $sql = "SELECT address FROM smtp_config";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $address = $row['address'];
    }
    return $address;
}

function getSmtpUser($conn) {
    $sql = "SELECT username, password FROM smtp_user WHERE blocked = 0";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $users[$row['username']] = descrypt($row['password']);
        }
    }
    return $users;
}

function getSmtpUserId($conn, $username) {
    $sql = "SELECT id FROM smtp_user WHERE username = '".$username."'";
    $result = $conn->query($sql);
    $id = 0;
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc(); 
        $id = $row['id'];
    }
    return $id;
}

// function getUsername($conn) {
//     $sql = "SELECT username FROM smtp_config";
//     $result = $conn->query($sql);
    
//     if ($result->num_rows > 0) {
//         $row = $result->fetch_assoc();
//         $username = $row['username'];
//     }
//     return $username;
// }

// function getPassword($conn) {
//     $sql = "SELECT password FROM smtp_config";
//     $result = $conn->query($sql);

//     if ($result->num_rows > 0) {
//         $row = $result->fetch_assoc();
//         $password = descrypt($row['password']);
//     }
    
//     return $password;
// }

function checkAdminEmail($conn, $name) {
    $sql = "SELECT id FROM admin_email WHERE name='".$name."'";
    $result = $conn->query($sql);
    $id = null;

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $id = $row['id'];
    }
    return $id;
}

function insertAdminEmail($conn, $name, $email) {
    $last_id = null;
    $now = date('Y-m-d H:i:s', time());
    $sql = "INSERT INTO admin_email (name, email, created_at, updated_at)
    VALUES ('".$name."', '".$email."', '".$now."', '".$now."')";
    if(mysqli_query($conn, $sql)) {
        $last_id = mysqli_insert_id($conn);
        echo "New admin_email created successfully. Last inserted ID is: " . $last_id."\n";
    } else {
        echo "\n\nError: " . $sql . "\n" . mysqli_error($conn)."\n\n";
    }
    return $last_id;
}

function insertEmailContent($conn, $sender_id, $subject, $content, $status = 1) {
    $content = addslashes($content);

    $last_id = null;
    $now = date('Y-m-d H:i:s', time());
    $sql = "INSERT INTO email_content (sender_id, subject, content, status, created_at, updated_at)
    VALUES ('".$sender_id."', '".$subject."', '"."$content"."', $status, '".$now."', '".$now."')";
    if(mysqli_query($conn, $sql)) {
        $last_id = mysqli_insert_id($conn);
        echo "New email_content created successfully. Last inserted ID is: " . $last_id."\n";
    } else {
        echo "\n\nError: " . $sql . "\n" . mysqli_error($conn)."\n\n";
    }
    return $last_id;
}

function checkUserEmail($conn, $email){
    $sql = "SELECT id FROM user_email WHERE email='".$email."'";
    $result = $conn->query($sql);
    $id = null;

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $id = $row['id'];
    }
    
    return $id;
}

function insertUserEmail($conn, $name, $email) {
    $last_id = null;
    $now = date('Y-m-d H:i:s', time());
    $sql = "INSERT INTO user_email (name, email, created_at, updated_at)
    VALUES ('".$name."', '".$email."', '".$now."', '".$now."')";
    if(mysqli_query($conn, $sql)) {
        $last_id = mysqli_insert_id($conn);
        echo "New user_email created successfully. Last inserted ID is: " . $last_id."\n";
    } else {
        echo "\n\nError: " . $sql . "\n" . mysqli_error($conn)."\n\n"."\n\n";
    }
    return $last_id;
}

function insertUserTag($conn, $user_id, $tag){
    $last_id = null;
    $now = date('Y-m-d H:i:s', time());

    if(!$tag_id = checkTag($conn, $tag)){
        $tag_id = insertTag($conn, $tag);
    }

    $sql = "INSERT INTO user_email_tag (user_email_id, tag_id)
    VALUES ('".$user_id."', '".$tag_id."')";
    if(mysqli_query($conn, $sql)) {
        $last_id = mysqli_insert_id($conn);
        echo "New user_email_tag created successfully. Last inserted ID is: " . $last_id."\n";
    } else {
        echo "\n\nError: " . $sql . "\n" . mysqli_error($conn)."\n\n"."\n\n";
    }
    return $last_id;
}

function checkTag($conn, $tag) {
    $sql = "SELECT id FROM tag WHERE title='".$tag."'";
    $result = $conn->query($sql);
    $id = null;

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $id = $row['id'];
    }
    
    return $id;
}

function insertTag($conn, $tag) {
    $last_id = null;
    $now = date('Y-m-d H:i:s', time());
    $sql = "INSERT INTO tag (title, created_at, updated_at)
    VALUES ('".$tag."', '".$now."', '".$now."')";
    if(mysqli_query($conn, $sql)) {
        $last_id = mysqli_insert_id($conn);
        echo "New tag created successfully. Last inserted ID is: " . $last_id."\n";
    } else {
        echo "\n\nError: " . $sql . "\n" . mysqli_error($conn)."\n\n"."\n\n";
    }
    return $last_id;
}

function insertOutboxEmail($conn, $sender_id, $user_email_id, $email_content_id, $smtp_user_id = 0, $status = 0) {
    $last_id = null;
    $now = date('Y-m-d H:i:s', time());
    $sql = "INSERT INTO outbox_email (sender_id, user_email_id, email_content_id, smtp_user_id, status, created_at, updated_at)
    VALUES ($sender_id, $user_email_id, $email_content_id, $smtp_user_id, $status, '".$now."', '".$now."')";
    if(mysqli_query($conn, $sql)) {
        $last_id = mysqli_insert_id($conn);
        echo "New outbox_email created successfully. Last inserted ID is: " . $last_id."\n";
    } else {
        echo "\n\nError: " . $sql . "\n" . mysqli_error($conn)."\n\n";
    }
    return $last_id;    
}

function updateOutboxEmailStatus($conn, $id, $status) {
    $sql = "UPDATE outbox_email SET status = $status WHERE id='".$id."'";
    
    if (mysqli_query($conn, $sql)) {
    echo "outbox-email Record updated successfully";
    } else {
    echo "\n\nError updating record: " . $conn->error;
    }
}

function getSmtpAccount($conn) {
    $sql = "SELECT username, password, host, port FROM smtp_account WHERE enabled = 1";
    $result = $conn->query($sql);
    $account = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $temp = [
                "username" => $row['username'],
                "password" => descrypt($row['password']),
                "host" => $row['host'],
                "port" => $row['port']
            ];
            array_push($account, $temp);
        }
    }
    return $account;
}

function sendMail($account, $senderName, $from, $to, $subject, $content) {
    $mail = new PHPMailer;

    // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    // Setup Mail
    $mail->isHTML(true);
    $mail->setFrom($from, $senderName);
    $mail->addAddress($to);
    $mail->Subject = $subject;
    $mail->Body = $content;

    // Setup SMTP
    $mail->isSMTP();
    $mail->Host = $account['host'];
    $mail->SMTPAuth = TRUE;
    $mail->SMTPSecure = 'tls';
    $mail->Username = $account['username'];
    $mail->Password = $account['password'];
    $mail->Port = $account['port'];

    // Try send the mail
    if ($mail->send()){
        return 1;
    } else {
        echo $mail->ErrorInfo;
        return 0;
    }
}

function descrypt($data) {
    $decryption_key = openssl_digest(php_uname(), 'MD5', TRUE); 
                
    // Descrypt the string 
    $data = openssl_decrypt($data, "AES-128-CBC", 
    $decryption_key, 0, '1c2c3c4c5c6c7c8c'); 
    return $data;
}
?>