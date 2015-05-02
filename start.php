<?php
/**
 * Elgg SendGrid plugin
 *
 * @package SendGrid
 */

elgg_register_event_handler('init', 'system', 'sendgrid_init');

require_once(dirname(__FILE__) . '/vendor/autoload.php');
require_once(dirname(__FILE__) . "/lib/functions.php");
require_once(dirname(__FILE__) . "/lib/hooks.php");

function sendgrid_init() {

    // Register the MQueue class
    elgg_register_class('sendgrid_queue', "$base/classes/MQueue.php");

    // Hook on email handler
	elgg_register_plugin_hook_handler("email", "system", "sendgrid_email_hook");

    // Register notification handler
    register_notification_handler("email", "sendgrid_notify_handler");

    // Allow the sendgrid webhook through
    elgg_register_plugin_hook_handler("public_pages", "walled_garden", "sendgrid_public_pages");

    elgg_register_page_handler('sendgrid', 'sendgrid_page_handler');

    elgg_register_action('sendgrid/test', dirname(__FILE__) . '/actions/test.php', 'admin');
}

function sendgrid_page_handler($page) {

    switch ($page[0]) {

        case 'webhook':
            sendgrid_process_webhook($page[1]);
            break;

        case 'test':
            sendgrid_test_email();
            break;
    }

    return true;
}

function sendgrid_notify_handler(ElggEntity $from, ElggUser $to, $subject, $message, $params = NULL) {

    global $CONFIG;

    if (!$from) {
        $msg = elgg_echo('NotificationException:MissingParameter', array('from'));
        throw new NotificationException($msg);
    }

    if (!$to) {
        $msg = elgg_echo('NotificationException:MissingParameter', array('to'));
        throw new NotificationException($msg);
    }

    if ($to->email == "") {
        $msg = elgg_echo('NotificationException:NoEmailAddress', array($to->guid));
        throw new NotificationException($msg);
    }

    // From
    $site = elgg_get_site_entity();
	
	$icon = isset($params['-user_icon-']) ? $params['-user_icon-'] : false;
	if (!$icon) {
		if (($from instanceof \ElggUser) || ($from instanceof \ElggGroup)) {
			if ($from->access_id == ACCESS_PUBLIC) {
				$icon = $from->getIconURL();
			}
		} else if (elgg_is_logged_in()) {
			$icon = elgg_get_logged_in_user_entity()->getIconURL();
		}
	}

    // If there's an email address, use it - but only if its not from a user.
    if (!($from instanceof ElggUser) && $from->email) {
        $from = $from->email;
    } else if ($site && $site->email) {
        // Use email address of current site if we cannot use sender's email
        $from = $site->email;
    } else {
        // If all else fails, use the domain of the site.
        $from = 'noreply@' . get_site_domain($CONFIG->site_guid);
    }
	
    // set options for sending
    $options = array(
        "to"          => $to->email,
        "to_name"     => $to->name,
        "from"        => $site->email,
        "from_name"   => $site->name,
        "subject"     => $subject,
        "html"        => nl2br($message),
        "text"        => $message,
		"-user_icon-" => $icon
    );

    if (is_array($params)) {
        $options = array_merge($params, $options);
    }

    return sendgrid_send_email($options);
}

function sendgrid_get_templates() {

    $user = elgg_get_plugin_setting('sendgrid_user', 'sendgrid');
    $pass = elgg_get_plugin_setting('sendgrid_pass', 'sendgrid');

    if ($user && $pass) {

        $templates[0] = ' --- Select --- ';

        $auth = base64_encode($user . ':' . $pass);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.sendgrid.com/v3/templates');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "Authorization: Basic $auth"
        ));
        curl_setopt($ch, CURLOPT_HTTPGET, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = json_decode(curl_exec($ch));

        if (!empty($response->templates)) {
            foreach ($response->templates as $t) {
                foreach ($t->versions as $v) {
                    $templates[$v->template_id] = $t->name . ' - ' . $v->name;
                }
            }
        }
    }

    return $templates;
}
