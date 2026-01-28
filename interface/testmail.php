<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

set_time_limit(1000);
$ignoreAuth = true;
require_once("globals.php");
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use OpenEMR\Common\Crypto\CryptoGen;

echo 'Testing Email Setting <br>';

$email = $GLOBALS['patient_reminder_sender_email'];
$user = $GLOBALS['SMTP_USER'];
$cryptoGen = new CryptoGen();
$password = $cryptoGen->decryptStandard($GLOBALS['SMTP_PASS']);
$port =   $GLOBALS['SMTP_PORT'];
$secure = $GLOBALS['SMTP_SECURE'];
$host = $GLOBALS['SMTP_HOST'];
$refId = "0092";
$body = "
        <table>
        <tr>
        <td ><strong>Test mail</strong></td>
        </tr>
        <tr>
        <td><p>hello this is an test mail from OpenEMR</p>
        </td>
        </tr>
        <tr>
        <td>Email</td><td>$email</td>
        </tr>
        </table>";

$mail = new PHPMailer(true);
try {
    $mail->SMTPDebug = true;
    $mail->isSMTP();
    $mail->IsHTML(true);
    $mail->Host = $host;
    $mail->SMTPAuth = true;
    $mail->Username = $user;
    $mail->Password = $password;
    $mail->SMTPSecure = $secure;
    $mail->Port = $port;
    echo $GLOBALS['patient_reminder_sender_email'] . '<br>';

    $mail->setFrom($GLOBALS['patient_reminder_sender_email'], 'Billing Coordinator');

    $mail->addAddress($email, 'Client');
    $mail->Subject = 'Testing outbound emails ';
    $mail->Body = $body;
    $mail->send();
    echo '<br><br>Message Sent. Please check email for results';
    die('after sending');
}
catch (\Exception $e)
{
    echo "Message could not be sent";
    echo "<pre>";
    echo "Mailer error: " . $mail->ErrorInfo;
}
