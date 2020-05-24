<?php

// sonoff-temperature.php
//
// Script which saves to log temperature from device Sonoff TH 16.
// Bish-bosh installed on NAS Synology reads the temperature and sends HTTP request to this script.
// When the temperature exceeds specified level, script sends an e-mail.

/***********
* Settings *
***********/

// main settings
$temperature_alert = "40"; // if this temperature is exceeded, you will get an e-mail
$pause_between_alert = "60"; // how many seconds wait before sending another e-mails (when temperature keeps exceeding alert level)

// email settings
$email_sending = "enabled"; // send e-mails or no? "enabled" or "disabled"
$email_smtp_server = "mail.host.com"; // SMTP server to send e-mails
$email_from = "ItIsMe"; // sender of e-mail (arbitrary name of sender)
$email_smtp_user = "user@host.com"; // sender of e-mail (SMTP server login)
$email_smtp_password = "password"; // sender's password (SMTP server password)
$email_smtp_port = "587"; // SMTP port
$emails = array('user@host.com','user2@host.com'); // e-mail addresses to send e-mails
$email_subject = "Alert! Temperature is TEMPERATURE degrees!"; // e-mail's subject, word TEMPERATURE will be changed for current temperature

// other settings
$days = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'); // names of week days in your language

/************
* Main part *
************/

// include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// get info
$date = date('Y.m.d');
$time = date('H:i:s');
$day = $days[date('w')];
$temperature = $_GET['temperature'];

// read last temperature
include("logs/current.php");

// if we didn't get any temperature, only pass last temperature and exit
if ($temperature == "") { echo "$temperature_last"; exit; }

// rotate log
unlink("logs/last.php");
rename("logs/current.php", "logs/last.php");

// save information about current temperature
$msg = "<?php \$date_last = \"$date\"; \$day_last = \"$day\"; \$time_last = \"$time\"; \$temperature_last = \"$temperature\"; ?>";
$log_file = fopen('logs/current.php', 'w');
fwrite($log_file, $msg);
fclose($log_file);

if ($temperature > $temperature_alert) // check if current temperature exceeds alert level
{
    if ($temperature > $temperature_last) // temperature has grown since last measurement
    {
	$email_subject = str_replace(TEMPERATURE, $temperature, $email_subject);

	// send e-mails
	if ($email_sending == "enabled") {
	    // let's check when last e-mails have been send to avoid spam when temperature keeps excceding alert level
	    $time_mail = time();
	    include("logs/last_mails_sent.php"); // read when last e-mail was sent
	    $time_mail_difference = $time_mail - $time_mail_last;
	    if ($time_mail_difference > $pause_between_alert) // enough time passed since last sending of e-mails
	    {
	        // send e-mail
		foreach($emails as $email)
	        {
		    $mail = new PHPMailer(true);
		    $mail->SMTPDebug = 0;

		    $mail->isSMTP();
    	            $mail->Host       = "$email_smtp_server";
	    	    $mail->SMTPAuth   = true;
	            $mail->Username   = "$email_smtp_user";
	            $mail->Password   = "$email_smtp_password";
	    	    $mail->SMTPSecure = 'tls';
		    $mail->Port       = "$email_smtp_port";

		    $mail->SMTPOptions = array(
			'ssl' => array(
			'verify_peer' => false,
			'verify_peer_name' => false,
			'allow_self_signed' => true
			)
		    );

	    	    //Recipients
	    	    $mail->setFrom("$email_smtp_user", "$email_from");
	    	    $mail->addAddress("$email");
	
	    	    // Content
	    	    $mail->isHTML(true);
	    	    $mail->CharSet = 'UTF-8';
	    	    $mail->Subject = "$email_subject";
	    	    $mail->Body    = "$date, $day, godz. $time";
	    	    $mail->send();
	    	    
	    	    // save information about time of sending these e-mails
		    $msg = "<?php \$time_mail_last = \"$time_mail\"; ?>";
		    $log_file = fopen('logs/last_mails_sent.php', 'w');
		    fwrite($log_file, $msg);
		    fclose($log_file);
	    	}
	    }
	}
    }
}

?>
