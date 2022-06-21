<?php
/*
 * Removes the LiveChat (the wp-live-chat-software-for-wordpress plugin, https://www.livechat.com/) notice from wp-admin
 */

add_action( 'admin_notices', function() {
  if( class_exists('LiveChat\Services\NotificationsRenderer') && ( empty($_GET['page']) || $_GET['page'] != 'livechat_settings' ) ) {
    echo "<!--debug wp-live-chat-software-for-wordpress -->\n";

    ob_start();

    add_action( 'admin_notices', function() {
      $html = ob_get_clean();
      $html = preg_replace( '~<div id="lc-notice-container"[\s\S]*?livechatinc.com[\s\S]*?</script>~', '<!--debug BusinessPress removed wp-live-chat-software-for-wordpress notice from here--> ', $html );
      echo $html;
    
    }, PHP_INT_MAX );

  }

}, PHP_INT_MIN );