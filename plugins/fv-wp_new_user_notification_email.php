<?php

/**
 * Change the core WordPress activation email subject line from "[Site name] Login Details" to "[Site Name] Set your password"
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

add_filter( 'wp_new_user_notification_email', 'fv_wp_new_user_notification_email', 10, 3 );

function fv_wp_new_user_notification_email( $email, $user, $blogname ) {
  $email['subject'] = __( '[%s] Set your password' );
  return $email;
}
