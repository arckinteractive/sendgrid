<?php

function sendgrid_send_email($options) {
    error_log("sendgrid_send_email() - Start");

    //sanitize from/to addresses
    $pattern = '/[A-Za-z0-9._%+-]+@[A-Za-z0-9_-]+\.([A-Za-z0-9_-][A-Za-z0-9_]+)/'; //regex for pattern of e-mail address

    if (!is_email_address($options['from'])) {
        preg_match($pattern, $options['from'], $from_matches);
        if ($from_matches) {
            $options['from'] = $from_matches[0];
        }
    }

    if (!is_email_address($options['to'])) {
        preg_match($pattern, $options['to'], $to_matches);
        if ($to_matches) {
            $options['to'] = $to_matches[0];
        }
    }

    $user = elgg_get_plugin_setting('sendgrid_user', 'sendgrid');
    $pass = elgg_get_plugin_setting('sendgrid_pass', 'sendgrid');

    $from_name = isset($options['from_name']) ? $options['from_name'] : elgg_get_site_entity()->name;

    $params = array();

    if (elgg_get_plugin_setting('ignore_sendgrid_ssl', 'sendgrid')) {
        $params['turn_off_ssl_verification'] = true;
    }

    if (!$log = sendgrid_log($options, 'sent')) {
        error_log("sendgrid_send_email() - Failed to store message: " . print_r($options, true));
    }

    if ($options['token']) {
        $options['from'] = preg_replace('/(\S+)@(\S+)/', '$1+' . $options['token'] . '@$2', $options['from']);
    }

    $sendgrid = new SendGrid($user, $pass, $params);
    $email    = new SendGrid\Email();
    $email->addTo($options['to'])->
            setFrom($options['from'])->
            setFromName($from_name)->
            setSubject($options['subject'])->
            setText($options['text']);

    if (!empty($options['bcc'])) {
        if (is_array($options['bcc'])) {
            $email->setBccs($options['bcc']);
        } else {
            $email->setBcc($options['bcc']);
        }
    }

    if (!empty($options['cc'])) {
        if (is_array($options['cc'])) {
            $email->setCcs($options['cc']);
        } else {
            $email->setCc($options['cc']);
        }
    }

    if ($template_id = sendgrid_template($options)) {

        $msg = $options['html'];

        $email->setHtml($msg);

        $email->addFilter('templates', 'enabled', 1);
        $email->addFilter('templates', 'template_id', $template_id);

        if (isset($options['sg_template_sub'])) {
            foreach ($options['sg_template_sub'] as $tag => $values) {
                $email->addSubstitution($tag, $values);
            }
        } else if ($options['-user_icon-']) {
            $email->addSubstitution('-user_icon-', array($options['-user_icon-']));
        } else if (elgg_is_logged_in()) {
            $email->addSubstitution('-user_icon-', array(elgg_get_logged_in_user_entity()->getIconURL()));
        }

    } else {
        $email->setHtml($options['html']);
    }

    if ($params['reply_to_email']) {
        $email->setReplyTo($params['reply_to_email']);
    }

    $email->setUniqueArgs(array(
        'to_guid'   => $options['to_guid'],
        'from_guid' => $options['from_guid'],
        'token'     => $options['token'],
        'guid'      => $log->guid,
    ));

    error_log("sendgrid_send_email() - Sending email to {$options['to']} from {$options['from']} with subject {$options['subject']}");

    $result = $sendgrid->send($email);

    if ($result->message == 'success') {
        return true;
    }

    error_log("sendgrid_send_email() - Status: " . print_r($result, true));

    return false;
}

/**
 * Handle message logging
 *
 * @param string   $subject       Message subject.
 * @param string   $message       Message body.
 * @param string   $type          sent or received
 * @param array    $params        Message parameters (to_guid, from_guid, template etc)
 *
 */
function sendgrid_log($params, $type, $msgId=null) {

    error_log("sendgrid_log() - Logging message...");

    $ia = elgg_set_ignore_access(true);

    $initiator      = elgg_get_logged_in_user_guid() ? elgg_get_logged_in_user_guid() : 'cron';
    $owner_guid     = $params['to_guid'] ? $params['to_guid'] : get_user_by_username('admin')->guid;
    $container_guid = $params['entity_guid'] ? $params['entity_guid'] : $owner->guid;

    // Create a new messaging token entity
    $entity                  = new ElggObject();
    $entity->subtype         = 'messaging_log';
    $entity->title           = $subject;
    $entity->description     = $params['html'];
    $entity->owner_guid      = $owner_guid;
    $entity->container_guid  = $container_guid;
    $entity->access_id       = ACCESS_PRIVATE;

    $entity->save();

    $entity->message_type = $type;
    $entity->initiator    = $initiator;

    if ($msgId) {
        $entity->msgid = $msgId;
    }

    file_put_contents('/tmp/DEBUG', print_r($params, true));

    $ignore = array(
        'entity',
        'sg_template_sub',
        'to_guid',
        'entity_guid'
    );

    foreach ($params as $key => $val) {

        if (in_array($key, $ignore)) {
            continue;
        }

        $entity->$key = $val;
    }

    elgg_set_ignore_access($ia);

    return $entity;
}

function sendgrid_process_webhook($hook) {

    error_log("sendgrid_process_webhook() - Received $hook webhook");

    switch ($hook) {

        case 'event':
            sendgrid_process_event_webhook();
            break;

        default:
            sendgrid_process_email_webhook();
            break;
    }
}

function sendgrid_process_event_webhook() {

    elgg_set_ignore_access(true);

    $postdata = json_decode(file_get_contents("php://input"));

    foreach ($postdata as $event) {
        if ($log = get_entity($event->guid)) {
            $log->annotate('sg_event', $event->event, ACCESS_LOGGED_IN);

            if ($event->event == 'delivered') {
                $log->msgid = $event->smtp-id;
            }
        }

        //error_log("sendgrid_process_event_webhook() - Ignoring event {$event->sg_event_id}. Not our event.");
    }

    elgg_set_ignore_access(false);
}

function sendgrid_process_email_webhook() {

    $headers = $_POST['headers'];

    if (!$headers || !preg_match('/sendgrid/', $headers)) {
        error_log('sendgrid_process_email_webhook() - Invalid message format');
        return;
    }

    error_log("sendgrid_process_email_webhook() - Have a valid message");

    // Ignore the message if we have already seen it
    preg_match('/Message-ID: <(\S+)>/', $headers, $matches);
    $msgId = $matches[1];

    error_log("sendgrid_process_email_webhook() - Message ID: $msgId");

    elgg_set_ignore_access(true);

    // Allow through some HTML -- Should links be allowed?
    $html = strip_tags($_POST['html'], "<b><strong><em><i><p><br><ul><li><ol><a>");

    if (!$text = $_POST['text']) {
        $h2t = new \Html2Text\Html2Text($_POST['html']);
        $text = $h2t->getText();
    }

    // Parse out the display name
    $from_name = sendgrid_get_name_from_rfc_email($_POST['from']);

    // Parse out the email address
    $from_email = sendgrid_get_email_from_rfc_email($_POST['from']);

    error_log("sendgrid_process_email_webhook() - From name: {$from_name}  From email: {$from_email}");

    // Parse out the token / token
    if (preg_match('/\S+\+(\S+)@\S+/', $_POST['to'], $matches)) {
        $token = $matches[1];
        error_log("sendgrid_process_email_webhook() - Message has token: {$token}");
    }

    $params = array(
        'headers'     => $headers,
        'msgid'       => $msgId,
        'to'          => $_POST['to'],
        'from'        => $_POST['from'],
        'from_name'   => $from_name,
        'from_email'  => $from_email,
        'subject'     => $_POST['subject'],
        'text'        => sendgrid_cleanup_reply($text),
        'html'        => sendgrid_cleanup_reply(null, $html),
        'sender_ip'   => $_POST['sender_ip'],
        'attachments' => $_POST['attachments'],
        'processed'   => 0,
        'token'       => $token,
    );

    $msg = sendgrid_log($params, 'receive');

    error_log("sendgrid_process_email_webhook() - Msg GUID: {$msg->guid} - Triggering plugin hook");

    if (elgg_trigger_plugin_hook("sendgrid", "receive", array('entity' => $msg), false)) {
        $msg->processed = 1;
        error_log("sendgrid_process_email_webhook() - Entity {$msg->guid} marked as processed");
    }

    elgg_set_ignore_access(false);

    return true;
}

function sendgrid_test_email() {

    admin_gatekeeper();

    $content = elgg_view('sendgrid/test');

    $layout = elgg_view_layout('one_column', array('content' => $content));

    echo elgg_view_page('SendGrid', $layout);
}

function sendgrid_get_name_from_rfc_email($rfc_email_string) {
    $name       = preg_match('/[\w\s]+/', $rfc_email_string, $matches);
    $matches[0] = trim($matches[0]);
    return $matches[0];
}

function sendgrid_get_email_from_rfc_email($rfc_email_string) {
    $mailAddress = preg_match('/(?:<)(.+)(?:>)$/', $rfc_email_string, $matches);
    return $matches[1];
}

/**
 * Attempts to parse an incomiong message up to the point where the original message starts if it is included.
 * Many clients auto include the previous message. We also strips tags and removes other unwanted elements here.
 *
 */
function sendgrid_cleanup_reply($text=null, $html=null) {

    $site_name = elgg_get_site_entity()->name;

    $pattern1 = "(\d{4}-\d{2}-\d{2} \d{2}:\d{2} \S+ Executive Networks ENsight)";
    $pattern2 = "(On \S+ \w+ \d+, \d+ at \S+ \S+, $site_name)";

    if ($html) {

        if (preg_match("/$pattern1/", $html, $matches)) {
            return strstr($html, $matches[1], true);
        } else if (preg_match("/$pattern2/", $html, $matches)) {
            return strstr($html, $matches[1], true);
        }

        return null;
    }

    $new_text = '';

    $text = preg_replace('/=0A/', "\n", strip_tags($text));
    $text = preg_replace('/=A0/', "\n", $text);

    foreach(preg_split("/((\r?\n)|(\r\n?))/", $text) as $line){
        if (preg_match('/____________________/', $line)) {
            break;
        } else if (preg_match("/$pattern1|$pattern2/", $line)) {
            break;
        }

        $new_text .= $line . "\n";
    }

    return trim($new_text);
}

function sendgrid_template($options) {

    // Template engine disabled by admin setting
    if (!$template_engine = elgg_get_plugin_setting('sendgrid_template', 'sendgrid')) {
        return false;
    }

    // Template engine disabled by plugin
    if (isset($options['sg_template_engine']) && $options['sg_template_engine'] === FALSE) {
        return false;
    }

    // Template ID overridden by plugin
    if (isset($options['sg_template_id'])) {
        return $options['sg_template_id'];
    }

    if (elgg_is_logged_in()) {
        return '717ed273-ee37-413c-84de-058aa5720911';
    }

    // Return default template ID if set
    return elgg_get_plugin_setting('sendgrid_template_id', 'sendgrid');
}

