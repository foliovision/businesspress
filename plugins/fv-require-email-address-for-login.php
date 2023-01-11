<?php
add_filter( 'authenticate', 'fv_require_email_address_for_login', PHP_INT_MAX, 2 );

function fv_require_email_address_for_login( $user, $username ) {

  if( $username && !is_email( trim($username) ) ) {
    return new WP_Error(
      'email_required',
      sprintf(
        __( '<strong>Error</strong>: Please use your e-mail address as the login user name.' ),
        $username
      )
    );
  }

  return $user;
}

/*
 * Extra check when logging in with Profile Builder Pro
 * It seems to change $_POST['log'] from email to user_login for some reason
 * So we detect email address here early on and skip our check which is done later
 */
add_action( 'login_init', 'fv_profile_builder_pro_login_with_email', 0 );

function fv_profile_builder_pro_login_with_email() {

  $wppb_generalSettings = get_option( 'wppb_general_settings', array() );

  if( !empty($wppb_generalSettings['loginWith']) && in_array( $wppb_generalSettings['loginWith'], array( 'email', 'usernameemail' ) ) ) {
    // Is it using email? Then skin our check
    if( !empty($_POST['log']) && is_email( trim($_POST['log']) ) ) {
      remove_filter( 'authenticate', 'fv_require_email_address_for_login', PHP_INT_MAX, 2 );    
    }
  }
}