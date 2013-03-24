<?php


/**** SETTINGS AREA ****/
$live               = false; // change this to true when you're ready to start sending autoreplies. false leaves it in testing mode and will not sent auto replies.
$debug              = true; //

/**** Settings for POP3/IMAP account (ie: reading emails) *****/
$email_receive_username     = 'your@gmailaccount.com';
$email_receive_password     = 'password';
$email_receive_mode = 'imap'; // or pop3
$email_receive_host = 'imap.gmail.com';
$email_receive_port = 993;
$email_receive_secure = true; // use SSL or not.
$mailbox            = 'INBOX';
$search_string      = 'UNSEEN SUBJECT "%Message sent via your marketplace profile%"';
// example searches: (see http://php.net/imap_search for more details)
//$search_string      = 'TO "you@site.com" SINCE 10-May-2011';
//$search_string      = 'UNSEEN'; // all unread emails
//$search_string      = ''; // all emails no matter what


/**** Settings for SMTP account (le: sending email replies) ****/
$email_send_host    = 'smtp.gmail.com';
$email_send_port    = '557';
$email_send_username = 'your@gmailaccount.com';
$email_send_password = 'password';

/**** END SETTINGS ****/
