<?php

/**
* Allow SendGrid to post to webhook
*
* @param string $hook
* @param string $type
* @param array $return_value
* @param array $params
* @return array
*/
function sendgrid_public_pages($hook, $type, $return_value, $params){
    $return_value[] = 'sendgrid/webhook/incoming';
    $return_value[] = 'sendgrid/webhook/event';
    return $return_value;
}


/**
* Hook to handle emails send by elgg_send_email
*
* @param string $hook
* @param string $type
* @param bool $return
* @param array $params
*      to      => who to send the email to
*      from    => who is the sender
*      subject => subject of the message
*      body    => message
*      params  => optional params
*/
function sendgrid_email_hook($hook, $type, $return, $params){

    $options = array(
        "html"    => nl2br($params['body']),
        "text"    => $params["body"]
    );

    return sendgrid_send_email(array_merge($params, $options));
}

