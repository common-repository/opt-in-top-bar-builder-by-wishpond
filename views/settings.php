<?php
  if (!current_user_can('activate_plugins')) {
    wp_die('Not enough permissions');
  }

  $token = WishpondUtilities::get_auth_token();

  global $error_message;
  global $notice_message;

  $error  = $error_message;
  $notice = $notice_message;
?>

<div class='wrap'>
  <?php screen_icon(); ?>

  <?php if (!empty($error) && $error): ?>
    <div class='error'><p><?php echo $error; ?></p></div>
  <?php endif; ?>

  <?php if (!empty($notice) && $notice): ?>
    <div class='updated'><p><?php echo $notice; ?></p></div>
  <?php endif; ?>

  <h2>Settings</h2>

  <?php if (isset($token)): ?>
    <form method='post' action=''>
      <h3>Wishpond Wordpress Token: <small><?php echo $token; ?></small></h3>

      <hr>

      <p><strong>Update Token: </strong></p>
      <input class='regular-text' type='text' name='token' value='<?php echo $token ?>'/>
      <br>

      <?php submit_button(); ?>
    </form>
  <?php endif; ?>
</div>
