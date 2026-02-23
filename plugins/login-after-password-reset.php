<?php

/**
 * Auto login user after password reset.
 *
 * When user sets the new password, he still has to log in.
 * This plugin will auto login the user after he sets the new password.
 * 
 * It's also useful when the user gets the "Login Details" email and has to set the password. So we log him in automatically.
 *
 * @package BusinessPress
 */

add_action('after_password_reset', 'fv_businesspress__login_after_password_reset', 10, 2);

function fv_businesspress__login_after_password_reset( $user, $new_pass ) {

  // Do not login if the user is not active, for FV Approve User plugin.
  if ( $user->user_status !== 0 ) {
    return;
  }

  // Clear any existing auth cookies
  wp_clear_auth_cookie();

  // Set the auth cookie
  wp_set_auth_cookie( $user->ID, true );

  // Set the current user
  wp_set_current_user( $user->ID );

  // Redirect to home page or dashboard
  wp_redirect( home_url() );
  exit;
}
