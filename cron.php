<?php

/**
 * Basic GMAIL Autoreply PHP Script.
 * Author: dtbaker.net
 * Date: 24th March 2013
 * Why? Because Gmail autoreplies get sent to Return-Path. So when autoreplying to Mandrill (etc..) emails the built in gmail autoreply goes to the Mandrill bounce return-path account.
 *
 * Instructions:
 *
 * 1) Enter your IMAP account password in the config.php settings file.
 *    (For Google, I recommend setting up 2-Step Auth and use an Application Specific Password. Google for more info or try this direct link: https://accounts.google.com/b/0/IssuedAuthSubTokens )
 * 2) Upload this file and config.php to a new folder on your website (eg: yourwebsite.com/emails/)
 * 3) Run this file in your browser to test (eg: yourwebsite.com/emails/cron.php)
 * 4) When it looks like it is working (and not going to send an autoreply to every email account in your system) change the "live" setting from "false" to "true" below
 * 5) Run this file again in your browser to test now that it is set to live
 * 6) Autoreplies should go out!
 * 7) Set this script up as a CRON job (eg: every 15 minutes) on your hosting account (ask your hosting provider for assistance in setting up a CRON job)
 *
 */



if(!function_exists('imap_open')){
    die('IMAP extension not available, please swap hosting providers.');
}

require('config.php');

$ssl = ($email_receive_secure) ? '/ssl' : '';
$host = '{'.$email_receive_host.':'.$email_receive_port.'/'.$email_receive_mode.$ssl.'/novalidate-cert}'.$mailbox;
if($debug)echo "Connecting to $host <br>\n";
$mbox = imap_open ($host, $email_receive_username, $email_receive_password);
if(!$mbox){
    echo 'Failed to connect to account. Error is: '.imap_last_error();
    imap_errors();
    exit;
}

$MC = imap_check($mbox);
if($debug)echo 'Connected successfully. Got this many messages: '.$MC->Nmsgs ."<br>\n";

$search_results = array(-1); // -1 is all messages
if($email_receive_mode=='imap' && $search_string){
    //imap_sort($mbox,SORTARRIVAL,0);
    // we do a hack to support multiple searches in the imap string.
    if(strpos($search_string,'||')){
        $search_strings = explode('||',$search_string);
    }else{
        $search_strings = array($search_string);
    }
    $search_results = array();
    foreach($search_strings as $this_search_string){
        $this_search_string = trim($this_search_string);
        if(!$this_search_string){
            return false;
        }
        if($debug)echo "Searching for $this_search_string <br>\n";
        $this_search_results = imap_search($mbox,$this_search_string);
        if($debug)echo " -- found ". ($this_search_results ? count($this_search_results) : 'no')." results <br>\n";
        print_r($this_search_results);
        if($this_search_results){
            $search_results = array_merge($search_results,$this_search_results);
        }
    }
    if(!$search_results){
        if($debug)echo "No search results for $search_string <br>\n";
        exit;
    }else{
        sort($search_results);
    }
}
imap_errors();

$sorted_emails = array();
foreach($search_results as $search_result){
    if($search_result>=0){
        $result = imap_fetch_overview($mbox,$search_result,0);
    }else{
        $result = imap_fetch_overview($mbox,"1:". min(100,$MC->Nmsgs),0);
    }
    foreach ($result as $overview) {
        if(!isset($overview->subject) && !$overview->date)continue; // skip these ones without dates and subjects?
        if(strpos($overview->subject,'Re:')!==false){
            if($debug){
                // not sure if this is the best thing to do. oh well.
                echo "Ignoring this email subject '".$overview->subject."' because it has 'Re:' in it. <br>\n";
            }
            continue;
        }
        $message_id = isset($overview->message_id) ? (string)$overview->message_id : false;
        $overview->time = strtotime($overview->date);
        $sorted_emails [] = $overview;
    }
}
function dtbaker_ticket_import_sort($a,$b){
    return $a->time > $b->time;
}
uasort($sorted_emails,'dtbaker_ticket_import_sort');
// finished sorted our emails into a nice $sorted_emails array.


$message_number = 0;

require_once 'PHPMailer/class.phpmailer.php';
require_once 'PHPMailer/class.smtp.php';


$mail = new PHPMailer;

$mail->CharSet = 'UTF-8';

$mail->IsSMTP();                                   // Set mailer to use SMTP
$mail->Host = $email_send_host;                    // Specify main and backup server
$mail->SMTPAuth = $email_send_smtpauth;            // Enable SMTP authentication
$mail->Username = $email_send_username;            // SMTP username
$mail->Password = $email_send_password;            // SMTP password
$mail->SMTPSecure = $email_send_smtpsecure;        // Enable encryption, 'ssl' also accepted
$mail->Port = $email_send_port;                    // Port



foreach($sorted_emails as $overview){
    
    $body = file_get_contents('template.html');

    $message_number++;
    $message_id = (string)$overview->message_id;
    if($debug){
        ?>
        <div style="padding:5px; border:1px solid #EFEFEF; margin:4px;">
            Found email: <strong>#<?php echo $message_number;?></strong>
            Date: <strong><?php echo $overview->date;?></strong> <br/>
            Subject: <strong><?php echo htmlspecialchars($overview->subject);?></strong> <br/>
            From: <strong><?php echo htmlspecialchars($overview->from);?></strong> <br/>
            To: <strong><?php echo htmlspecialchars($overview->to);?></strong> <br/>
            Message ID: <strong><?php echo htmlspecialchars($message_id);?></strong>
        </div>
        <?php
    }
    if(!$live){
        if($debug){
            echo "Not processing this email because we are not in 'live' mode. Please change \$live to true when you are ready. <br>\n";
        }
        continue;
    }
    // mark this email as seen. useful if we're searching based on "UNSEEN" emails.
    $status = imap_setflag_full($mbox, $overview->msgno, "\\Seen");


	//clear addresses
	$mail->ClearAddresses();
	
	$mail->From = $email_from;
	$mail->FromName = $email_from_name;
	
	//Add a recipient
	$mail->AddAddress($overview->from);
	
	// Add BCC
	if(!empty($email_bcc)) $mail->AddBCC($email_bcc);
	
	$mail->IsHTML(true);
		
	$mail->Subject = 'Re: '.$overview->subject;
	
	$replace = array(
		'{preheader}' => '',
		'{subject}' => $mail->Subject,
		'{senddate}' => date($timeformat, strtotime($overview->date)),
		'{date}' => date($timeformat),

	);
	
	//replace placeholders
	$body = str_replace(array_keys($replace), array_values($replace), $body);
	
	//remove all missing placeholders
	$body = preg_replace('#{\w+}#', '', $body);
	
	$mail->Body = $body;
	
	if(!$mail->Send()) {
        if($debug){
		   echo 'Message could not be sent.';
		   echo 'Mailer Error: ' . $mail->ErrorInfo;
		   continue;
        }
	}else{
		if($debug){
		   echo 'Message sent to '.$overview->from;
		}
	}
}
