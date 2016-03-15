<?php

    // $curl -H "Content-Type: application/json" -u sendgrid_username -X POST -d '{"name":"example_name"}' https://api.sendgrid.com/v3/templates
    if ($vars['entity']->sendgrid_user) {

        $templates[0] = ' --- Select --- ';

        $auth = base64_encode($vars['entity']->sendgrid_user . ':' . $vars['entity']->sendgrid_pass);

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
?>

<script type="text/javascript">
    $(document).ready(function(){
        $("#sendgrid-template").click(function() {
            var check = $(this);
            if ( check.is(':checked') ) {
                $("#sg-tmpl-sel").show();
            } else {
                $("#sg-tmpl-sel").hide();
            }
        });
    });
</script>

<fieldset>

    <br />

    <div class="elgg-module elgg-module-inline">
        <div class="elgg-head"><h3>SendGrid Settings (Outgoing)</h3></div>
        <div class="elgg-body">

            <p><strong><a target="_blank" href="/mod/sendgrid/dashboard">SendGrid Dashboard</a></strong></p>

            <div>
                <label><?php echo elgg_echo('Username'); ?>:</label>
                <p>
                    <?php echo elgg_view('input/text', array('name' => 'params[sendgrid_user]', 'value' => $vars['entity']->sendgrid_user)); ?>
                </p>
            </div>

            <div>
                <label><?php echo elgg_echo('Password'); ?>:</label>
                <p>
                    <?php echo elgg_view('input/password', array('name' => 'params[sendgrid_pass]', 'value' => $vars['entity']->sendgrid_pass)); ?>
                </p>
            </div>

            <p>
                <label>Use Template Engine: </label>
                <?php echo elgg_view('input/checkbox', array(
                    'name'    => 'params[sendgrid_template]',
                    'id'      => 'sendgrid-template',
                    'value'   => 1,
                    'checked' => $vars['entity']->sendgrid_template ? 'checked' : null,
                    'default' => 0
                ));?>
            </p>

            <div id="sg-tmpl-sel" <?php if (!$vars['entity']->sendgrid_template) echo 'style="display:none;"'; ?>>
                <label class="label">Template:
                <?php echo elgg_view("input/dropdown", array("name" => "params[sendgrid_template_id]", "options_values" => $templates, 'value' => $vars['entity']->sendgrid_template_id)); ?>   
                </label>
            </div>

			<p style="margin-top:10px;">
                <label>Ignore SSL (don't use on production): 
				<?php echo elgg_view('input/dropdown', array(
						'name' => 'params[ignore_sendgrid_ssl]',
						'value' => $vars['entity']->ignore_sendgrid_ssl,
						'options_values' =>	array(
							0 => elgg_echo('option:no'),
							1 => elgg_echo('option:yes')
						)
					));
				?>
				</label>
            </p>

        </div>
    </div>

</fieldset>



