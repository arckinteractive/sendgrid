<?php

    $user = elgg_get_logged_in_user_entity();

?>


<form method="post" action="/action/sendgrid/test" class="elgg-form elgg-form-blog-save">

  <fieldset>

    <?php echo elgg_view('input/securitytoken'); ?>

    <div>
        <label>To (Username)</label>
        <input type="text" value="<?php echo $user->username; ?>" name="to" class="elgg-input-text">
    </div>

    <div>
        <label>Cc</label>
        <input type="text" value="" name="cc" class="elgg-input-text" placeholder="Comma delimited email addresses">
    </div>

    <div>
        <label>Bcc</label>
        <input type="text" value="" name="bcc" class="elgg-input-text" placeholder="Comma delimited email addresses">
    </div>

    <div>
        <label>Subject</label>
        <input type="text" value="SendGrid Email Test" name="subject" class="elgg-input-text">
    </div>

    <div>
        <label>Body</label>
        <textarea name="message" class="elgg-input-longtext" rows="4">Cupcake ipsum dolor sit amet. Donut dragée chocolate cake. I love chocolate cake cupcake croissant cookie biscuit icing cotton candy. Bear claw wafer pie oat cake dragée. Sweet roll gummi bears pudding wafer wafer dragée I love. Gummies soufflé powder chocolate halvah tart I love fruitcake. Bear claw tiramisu bonbon pie jelly-o gummies. I love chocolate cake cookie sweet carrot cake jujubes. Chocolate cake jelly-o carrot cake tiramisu ice cream sugar plum macaroon. Bonbon I love gingerbread.</textarea>
    </div>

    <div>
        <input type="submit" value="Send" name="send" class="elgg-button elgg-button-submit">
    </div>

  </fieldset>
</form>
