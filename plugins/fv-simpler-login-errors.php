<?php
/*
  * We do not want to give too many hits if somebody is trying to guess the password.
  * So we do not let them know if the user name or email was invalid
  */

// Core WordPress
add_action( 'wp_error_added', 'fv_hide_exact_login_error', PHP_INT_MAX, 4 );

function fv_hide_exact_login_error( $code, $message, $data, $wp_error ) {
  if( in_array( $code, array( 'invalid_username', 'incorrect_password' ) ) ) {

    // We need to remove the original error code and put in new error.
    // Becaus if WordPress sees there is invalid_username it will empty the user name field.
    // And that is another hint to the password crackers!
    $wp_error->remove( $code );

    $wp_error->errors[ 'incorrect_password' ] = array( __( '<strong>Error</strong>: The username or password you entered is incorrect.' ) );
  }
}

// Profile Builder Pro
add_filter( 'wppb_login_wp_error_message', 'fv_wppb_login_wp_error_message', PHP_INT_MAX, 2 );

function fv_wppb_login_wp_error_message( $error_string, $user ) {

  $error_string = str_replace( array(
      __('The password you entered is incorrect.', 'profile-builder'),
      __('Invalid email.', 'profile-builder'),
      __('Invalid username or email.', 'profile-builder'),
      __('Invalid username.', 'profile-builder')
    ), 
    __('The username or password you entered is incorrect.', 'profile-builder'),
    $error_string
  );

  return $error_string;
}