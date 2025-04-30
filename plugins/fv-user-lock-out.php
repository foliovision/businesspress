<?php

class FV_User_Lock_Out {

  function __construct() {
    // If the user had more than 20 bad attempts, with last attempt in last 24 hours, stop the login and email the user
    add_filter( 'authenticate', array( $this, 'prevent_authentication' ), PHP_INT_MAX, 3 );

    // processig of the user account unlocking via email
    add_action( 'login_form_unlock', array( $this, 'login_form_unlock' ) );

    // Record number of bad login attempts and last time of bad login attempt
    add_action( 'wp_login_failed', array( $this, 'wp_login_failed' ), 10, 2 );

    // Show unlock confirmation when it succeeds
    add_filter( 'wp_login_errors', array( $this, 'show_unlock_confirmation' ), PHP_INT_MAX, 3 );

    // Password reset should unlock the account
    add_action( 'password_reset', array( $this, 'password_reset' ), 10, 2 );

    add_filter( 'manage_users_columns', array( $this, 'admin_column' ) );
    add_filter( 'manage_users_custom_column', array( $this, 'admin_column_content' ), 10, 3 );

    add_action( 'wp_ajax_fv_user_lock_out_unlock', array( $this, 'admin_ajax') );

    // The "Unlock" link to allow your account login should not expire in 1, but 3 days
    add_filter( 'password_reset_expiration', array( $this, 'password_reset_expiration' ) );
  }

  function admin_ajax() {
    if( !wp_verify_nonce($_POST['nonce'], 'fv_user_lock_out_unlock='.$_POST['user_id']) ) {
      wp_send_json( array( 'error' => 'Nonce error.' ) );
    }
    
    $this->remove_lock( $_POST['user_id'] );

    if( function_exists("SimpleLogger") ) {
      $user = get_user( $_POST['user_id'] );
      SimpleLogger()->info( 'Admin unlocked locked out user account for: ' . $user->user_email );
    }

    wp_send_json( array( 'message' => 'Done, user will be able to log in again.' ) );
  }

  function admin_column( $columns ) {
    $columns['fv_user_lock_out'] = "Locked Out?";
    return $columns;
  }

  function admin_column_content( $content, $column_name, $user_id ) {

    if( $column_name == 'fv_user_lock_out' && $data = $this->is_user_locked_out( $user_id ) ) {
      $content = '<div data-fv_user_lock_out_unlock_wrap="'.$user_id.'">';
      $content .= '<span title="BusinessPress has detected '.$data['count'].' bad login attempts and has blocked further logis for this account." style="display: inline-flex; color: #fff; border-radius: 3px; line-height: 30px; padding: 0 0.75rem; margin-right: 0.35rem; background: #AF4c50">Locked Out</span>';
      $content .= '<div class="row-actions"><a href="#" data-fv_user_lock_out_unlock="'.$user_id.'" data-nonce="'.wp_create_nonce('fv_user_lock_out_unlock='.$user_id).'">Unlock</a></div>';
      $content .= '</div>';

      add_action( 'admin_footer', array( $this, 'admin_script' ) );
    }

    return $content;
  }

  function admin_script() {
    ?>
<script>
jQuery( function($) {
  $('a[data-fv_user_lock_out_unlock]').on( 'click', function() {
    var link = $(this),
      user_id = link.data('fv_user_lock_out_unlock');

    $.post( ajaxurl, {
      'action': 'fv_user_lock_out_unlock',
      'nonce': link.data('nonce'),
      'user_id': user_id
    }, function( response ) {
      if( response.error ) {
        alert( response.error );
      } else {
        alert( response.message );

        $('[data-fv_user_lock_out_unlock_wrap='+user_id+']').remove();
      }
    });
  });
});
</script>
    <?php
  }

  function is_user_locked_out( $user_id ) {
    $count = get_user_meta( $user_id, '_fv_bad_logins_count', true );
    $time = get_user_meta( $user_id, '_fv_bad_logins_last', true );
    if( $count > 20 && $time + DAY_IN_SECONDS > time() ) {
      return array(
        'count' => $count,
        'time' => $time
      );
    }

    return false;
  }

  function lockout_email( $user ) {
    $user_id = $user->ID;
  
    $last_lockout = get_user_meta( $user_id, 'fv_user_lockout_email', true );
  
    // Only send email once per week
    if( $last_lockout && ( $last_lockout + WEEK_IN_SECONDS ) > time() ) {
      return;
    }
  
    update_user_meta( $user_id, 'fv_user_lockout_email', time() );
  
    $key = get_password_reset_key( $user );
  
    $message = __( 'Hi %1$s,

We have detected too many failed login attempts for your user account. If you had no login issues recently, then there might be automated login attempts targeted towards your user account.

Only click this link if you are trying to log in yourself <strong>right now</strong>: <a href="%2$s">Unlock Login</a>

If you have a simple password it\'s possible that it was compromised. In that case click this link to set a more complex password: <a href="%3$s">Password Reset</a>

Regards,
All at %4$s
%5$s' );

    wp_mail(
      $user->user_email,
      sprintf( __( '[%s] Failed login attempts detected' ), wp_specialchars_decode( get_option( 'blogname' ) ) ),
      sprintf(
        wpautop( $message ),
        $user->display_name,
        network_site_url( "wp-login.php?action=unlock&ukey=$key&login=" . rawurlencode( $user->user_login ), 'login' ),
        network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user->user_login ), 'login' ),
        wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ),
        home_url()
      ),
      array('Content-Type: text/html; charset=UTF-8')
    );
  }

  function login_form_unlock() {

    // Stop processing HEAD request which might come from Outlook
    if ( ! empty( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
      wp_redirect( wp_login_url() );
      exit;
    }

    if ( isset( $_GET['error'] ) ) {
      $errors = new WP_Error();
      if ( 'invalidkey' === $_GET['error'] ) {
        $errors->add( 'invalidkey', __( '<strong>Error</strong>: Your unlock link appears to be invalid. Please reset your password below.' ) );
      } elseif ( 'expiredkey' === $_GET['error'] ) {
        $errors->add( 'expiredkey', __( '<strong>Error</strong>: Your unlock link has expired. Please reset your password below.' ) );
      }
  
      login_header( __( 'Unlock Login' ), '', $errors );    
      ?>
      <a class="button" href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php _e( 'Password Reset' ); ?></a>
      <?php
      login_footer();
      exit;
    }
  
    $user = check_password_reset_key( $_GET['ukey'], $_GET['login'] );
    if ( ! $user || is_wp_error( $user ) ) {
      if ( $user && $user->get_error_code() === 'expired_key' ) {
        wp_redirect( site_url( 'wp-login.php?action=unlock&error=expiredkey' ) );
      } else {
        wp_redirect( site_url( 'wp-login.php?action=unlock&error=invalidkey' ) );
      }
      exit;
    }
  
    $user_id = $user->ID;
  
    $this->remove_lock( $user_id );

    if( function_exists("SimpleLogger") ) {
      SimpleLogger()->info( 'User unlocked his locked out user account: ' . $user->user_email );
    }
  
    wp_redirect( add_query_arg( 'unlocked', true, wp_login_url() ) );
    exit;
  }

  function password_reset( $user ) {
    $this->remove_lock( $user->ID );

    if( function_exists("SimpleLogger") ) {
      SimpleLogger()->info( 'Password reset unlocked locked out user account for: ' . $user->user_email );
    }
  }

  function password_reset_expiration( $expiration_duration ) {
    if ( ! empty( $_GET['action'] ) && 'unlock' === $_GET['action'] ) {
      $expiration_duration = 3 * DAY_IN_SECONDS;
    }
    return $expiration_duration;
  }

  function prevent_authentication( $user_logged_in, $username, $password) {
    $user = get_user_by( 'login', $username );
    if( !$user ) {
      $user = get_user_by( 'email', $username );
    }

    if( $user ) {
      if( $this->is_user_locked_out( $user->ID ) ) {

        $this->lockout_email($user);

        return new WP_Error( 'authentication_failed', __( '<strong>Error</strong>: Too many failed attempts. Please check your email to log in again.' ) );
      }
    }

    return $user_logged_in;
  }

  // Remove block, but backup old values
  function remove_lock( $user_id ) {
    foreach( array(
      '_fv_bad_logins_last',
      '_fv_bad_logins_count',
      'fv_user_lockout_email'
    ) AS $meta_key ) {
      $meta_value = get_user_meta( $user_id, $meta_key, true );
      delete_user_meta( $user_id, $meta_key );
      update_user_meta( $user_id, $meta_key.'_previous', $meta_value );
    }
  }

  function show_unlock_confirmation( $errors ) {
    if( empty($errors) ) {
      $errors = new WP_Error();
    }
  
    if( !empty($_GET['unlocked']) && is_wp_error($errors) ) {
      if( !$errors->has_errors() ) {
        $errors->add( 'unlocked', __( '<strong>Success</strong>: Your user account has been unlocked, you can log in again.' ) );
      }
    }
    return $errors;
  }

  /**
   * Record number of bad login attempts and last time of bad login attempt.
   * Not if user just provided the username while the email address is required.
   * 
   * @param string $username
   * @param WP_Error $error Only available with WordPress 5.4 and later.
   *
   * @return void
   */
  function wp_login_failed( $username, $error = false ) {

    // Ignore if using "Require Email Address for Login"
    if( $error && $error->get_error_code() == 'email_required' ) {
      return;
    }

    // If using WordPress before 5.4, we need to check if $_POST['log'] is not an email address
    if ( ! is_email( $_POST['log'] ) ) {
      global $businesspress;
      // And if we have "Require Email Address for Login" enabled, then we don't need to record anything as it's a login attempt that cannot succeed
      if ( $businesspress->get_setting( 'login-email-address' ) ) {
        return;
      }
    }

    $user = get_user_by( 'login', $username );
    if( !$user ) {
      $user = get_user_by( 'email', $username );
    }

    if( $user ) {
      $count = get_user_meta( $user->ID, '_fv_bad_logins_count', true );
      if( !$count ) {
        $count = 0;
      }

      $count++;

      update_user_meta( $user->ID, '_fv_bad_logins_count', $count );
      update_user_meta( $user->ID, '_fv_bad_logins_last', time() );
    }

    if( $this->is_user_locked_out( $user->ID ) ) {
      $this->lockout_email($user);
    }
  }

}

new FV_User_Lock_Out;