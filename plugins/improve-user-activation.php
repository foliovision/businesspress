<?php

/**
 * Improve User Activation.
 *
 * Make sure user will not be able to log in until he sets the password, if sending out the "Login Details" email.
 *
 * Increases the lifetime of the activation link to 6 months from 1 day.
 *
 * @package BusinessPress
 */

/**
 * Show error when logging in if user has not set the password using the link from email notification.
 */
add_filter( 'authenticate', 'fv_businesspress_user_activation__login_check', PHP_INT_MAX, 2 );

function fv_businesspress_user_activation__login_check( $user, $username ) {

  // Copy $user into a new variable to make sure out changes to it will not affect the login process.
  $user_to_check = $user;

  /**
   * If the user is false or WP_Error, then it means the authentication failed.
   * So we need to get the user by username or email.
   */
  if ( ! $user_to_check || is_wp_error( $user_to_check ) ) {
    $user_to_check = get_user_by( 'login', $username );

    if ( ! $user_to_check ) {    
      $user_to_check = get_user_by( 'email', $username );
    }
  }

  /**
   * If the user has an activation key, then we need to guess if it's the one sent in "Login Details" email.
   */
  if ( $user_to_check && ! empty( $user_to_check->user_activation_key ) ) {
    // The activation key is in the format: {timestamp}:{activation_key}
    $user_activation_key = explode( ':', $user_to_check->user_activation_key );

    /**
     * To detect if it's from the "Login Details" email we check if $user_activation_date happened
     * within 5 minutes after $user->user_registered. We do this to avoid changing the behavior for
     * password reset links.
     */
    if (
      $user_activation_key[0] >= strtotime( $user_to_check->user_registered ) &&
      $user_activation_key[0] <= strtotime( $user_to_check->user_registered ) + 5 * 60
    ) {
      return new WP_Error(
        'activation_required',
        '<p>' . __( '<strong>Error:</strong> You did not set your password yet.', 'businesspress' ) . '</p>' .
        '<p>' . __( 'Please check your mailbox for the link to set your password.', 'businesspress' ) . '</p>' .
        '<a href="' . wp_lostpassword_url() . '">' .__( 'Request new link', 'businesspress' ) . '</a>'
      );
    }
  }

  return $user;
}

/**
 * Normally the link to set a password expires in 24 hours.
 * We extend it to 6 months.
 */
add_filter( 'password_reset_expiration', 'fv_businesspress_user_activation__expiration' );

function fv_businesspress_user_activation__expiration( $expiration ) {
  return 6 * MONTH_IN_SECONDS;
}

/**
 * Add a note for the "Send User Notification" checkbox when creating a new user.
 * When you are adding a user in wp-admin you should understand that if you choose to send that notification, it will require the user to set the password using the link in that email notification.
 */
add_action( 'user_new_form', 'fv_businesspress_user_activation_admin_notice' );

function fv_businesspress_user_activation_admin_notice() {
  ?>
    <script>
    jQuery( 'label[for=send_user_notification]' ).append( '<p>Email will contain a <strong>link</strong> to set user password. User will <strong>not</strong> be able to log in until he sets the password <span class="dashicons dashicons-info" title="This improvement is done by BusinessPress plugin."></span>.</p>' );
    </script>
  <?php
}
