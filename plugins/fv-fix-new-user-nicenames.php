<?php
/*
 * If the user login is not provided during registration, WordPress uses email address.
 * Then the email address as the login makes its way to user_nicename field which is used for author URLs and comment classes!
 * The email address is also used for user display_name.
 * 
 * We avoid all of that here.
 */

add_filter( 'wp_pre_insert_user_data', 'fv_fix_new_user_nicenames', PHP_INT_MAX, 3 );

function fv_fix_new_user_nicenames( $data, $update, $user_id ) {

  $user_email = $data['user_email'];
  $user_login = $data['user_login'];
  $user_nicename = $data['user_nicename'];

  $email_parts = explode( '@', $user_email );

  // Is Display Name the same as email? Just use the username part!
  if( !empty($data['display_name']) && $data['display_name'] == $user_email ) {
    $data['display_name'] = $email_parts[0];
  }

  // Is the user_nicename just sanitized user_email? That's not good as then it's easy to figure out what's the user email address
  // The sanitization below is how wp_insert_user() in wp-includes/user.php creates user_nicename from user_login
  if( sanitize_title( mb_substr( $user_email, 0, 50 ) ) == $user_nicename ) {

    // Use Display Name if it's there and it's not just the email address
    if( !empty($data['display_name']) ) {
      $user_nicename = sanitize_title( mb_substr( $data['display_name'], 0, 50 ) );

    // ...otherwise use user part of email address
    } else {
      $user_nicename = $email_parts[0];
    }

    global $wpdb;

    // Coppied from wp_insert_user() of wp-includes/user.php
    $user_nicename_check = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->users WHERE user_nicename = %s AND user_login != %s LIMIT 1", $user_nicename, $user_login ) );

    if ( $user_nicename_check ) {
      $suffix = 2;
      while ( $user_nicename_check ) {
        // user_nicename allows 50 chars. Subtract one for a hyphen, plus the length of the suffix.
        $base_length         = 49 - mb_strlen( $suffix );
        $alt_user_nicename   = mb_substr( $user_nicename, 0, $base_length ) . "-$suffix";
        $user_nicename_check = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->users WHERE user_nicename = %s AND user_login != %s LIMIT 1", $alt_user_nicename, $user_login ) );
        $suffix++;
      }
      $user_nicename = $alt_user_nicename;
    }

    $data['user_nicename'] = $user_nicename;

  }

  return $data;
}