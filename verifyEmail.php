<?php
require_once 'init.php';
require_once 'util.php';
requireLogin();
connectDB();
bifPageheader('verify e-mail');

$userid = $_SESSION['userid'];
$row = dbQueryByID('select email from user where id=?',$userid);
if ($row)
    {
    $addr = $row['email'];
    $subject = 'Infringement festival e-mail verification';
    $code = md5('bif-' . strtolower($addr));
    $body = "Thanks for signing up to be a part of the Buffalo Infringement Festival.\r\nTo verify that your e-mail address is correct, please click on this link: http://infringebuffalo.org/db2/confirmEmail.php?id=" . $_SESSION['userid'] . "&code=" . $code . "\r\n";
    log_message("verifying address $addr");
    $addresses = $addr . ", scheduler@infringebuffalo.org";
    if (loggedMail($addresses, $subject, $body))
        {
        echo '<p>A verification e-mail has been sent to ' . $addr . '</p><p>Please check the message and follow the included link to complete the verification.</p><p>If you don\'t see the e-mail in the next few minutes, try checking your spam folder.</p>';
        }
    else
        {
        echo '<p>I\'m sorry, but there was an error in trying to send a verification e-mail to ' . $addr . '</p><p>Please contact depape@buffalo.edu for assistance.</p>';
        }
    }
else
    {
    echo '<p>I\'m sorry, but there was an error in looking up your e-mail address.</p><p>Please contact depape@buffalo.edu for assistance.</p>';
    }

bifPagefooter();
?>
