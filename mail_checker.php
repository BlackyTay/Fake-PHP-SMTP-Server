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
$address = getAddress($conn); //smtp.goodsane.com
$port = getPort($conn);

if (count($pendingMails = getPendingEmail($conn)) > 0) {
    echo "Pending Emails : ".count($pendingMails)."\r\n";
    $smtp_account = getSmtpAccount($conn);
    foreach($pendingMails as $mail) {
        
        $status = 0;
        foreach($smtp_account as $account) {
            if(($status=sendMail($account, $mail['senderName'], $mail['senderEmail'], $mail['recipient'], $mail['subject'], $mail['content']))==1) {
                echo "Successfully Sent: ".$mail['id']."\r\n";
                break;
            }
        }

        if ($status == 1) {
            echo "Changing Status\r\n";
            updateOutboxEmailStatus($conn, $mail['id'], $status);
        }
    }
}

die("\r\n******** No Pending Mail ********\r\n");

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

function getPendingEmail($conn) {
    echo "Getting Pending Emails\r\n";
    $sql = "SELECT id, sender_id, user_email_id, email_content_id FROM outbox_email WHERE status = 0 ORDER BY id ASC";
    $result = $conn->query($sql);
    $pendingMails = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $sender = getAdminEmail($conn, $row['sender_id']);
            $recipient = getUserEmail($conn, $row['user_email_id']);
            $mail = getEmailContent($conn, $row['email_content_id']);
            $temp = [
                "id" => $row['id'],
                "senderName" => $sender['name'],
                "senderEmail" => $sender['email'],
                "recipient" => $recipient['email'],
                "subject" => $mail['subject'],
                "content" => $mail['content']
            ];
            array_push($pendingMails, $temp);
        }
    }
    return $pendingMails;
}

function getAdminEmail($conn, $sender_id) {
    $data = null;
    $sql = "SELECT name, email FROM admin_email WHERE id = $sender_id";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $data = [
            'name' => $row['name'],
            'email' => $row['email']
        ];
    }
    return $data;
}

function getUserEmail($conn, $user_email_id) {
    $data = null;
    $sql = "SELECT name, email FROM user_email WHERE id = $user_email_id";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $data = [
            'name' => $row['name'],
            'email' => $row['email']
        ];
    }
    return $data;
}

function getEmailContent($conn, $email_content_id) {
    $data = null;
    $sql = "SELECT subject, content FROM email_content WHERE id = $email_content_id";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $data = [
            'subject' => $row['subject'],
            'content' => $row['content']
        ];
    }
    return $data;
}

function updateOutboxEmailStatus($conn, $id, $status) {
    echo "Updateing Outbox Status\r\n";
    $sql = "UPDATE outbox_email SET status = $status WHERE id='".$id."'";
    
    if (mysqli_query($conn, $sql)) {
    echo "outbox-email Record updated successfully\r\n";
    } else {
    echo "\n\nError updating record: " . $conn->error."\r\n";
    }
}

function getSmtpAccount($conn) {
    echo "Getting SMTP\r\n";
    $sql = "SELECT username, password, host, port FROM smtp_account";
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
    echo "Sending Email: $subject\r\n";
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