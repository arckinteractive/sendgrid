<?php

$to = get_input('to');
$cc = get_input('cc');
$bcc = get_input('bcc');
$subject = get_input('subject');
$message = get_input('message');

$user = get_user_by_username($to);

$params = array(
    'cc' => $cc, 
    'bcc' => $bcc
);

//elgg_send_email(elgg_get_site_entity()->email, $user->email, $subject, $message, $params);

notify_user($user->guid, elgg_get_site_entity()->guid, $subject, $message, $params);

forward(REFERER);
