<?php

class FV_User_Lock_Out {

  function __construct() {
    // If the user had more than 20 bad attempts, with last attempt in last 24 hours, stop the login and email the user
    add_filter( 'authenticate', array( $this, 'prevent_authentication' ), PHP_INT_MAX, 3 );

    // processig of the user account unlocking via email
    add_action( 'login_form_unlock', array( $this, 'login_form_unlock' ) );

    // Record number of bad login attempts and last time of bad login attempt
    add_action( 'wp_login_failed', array( $this, 'wp_login_failed' ) );

    // Show unlock confirmation when it succeeds
    add_filter( 'wp_login_errors', array( $this, 'show_unlock_confirmation' ), PHP_INT_MAX, 3 );

    // Password reset should unlock the account
    add_action( 'password_reset', array( $this, 'password_reset' ), 10, 2 );

    add_filter( 'manage_users_columns', array( $this, 'admin_column' ) );
    add_filter( 'manage_users_custom_column', array( $this, 'admin_column_content' ), 10, 3 );

    add_action( 'wp_ajax_fv_user_lock_out_unlock', array( $this, 'admin_ajax') );
  }

  function admin_ajax() {
    if( !wp_verify_nonce($_POST['nonce'], 'fv_user_lock_out_unlock='.$_POST['user_id']) ) {
      wp_send_json( array( 'error' => 'Nonce error.' ) );
    }
    
    $this->remove_lock( $_POST['user_id'] );

    wp_send_json( array( 'message' => 'Done, user will be able to log in again.' ) );
  }

  function admin_column( $columns ) {
    $columns['fv_user_lock_out'] = "Locked Out?";
    return $columns;
  }

  function admin_column_content( $content, $column_name, $user_id ) {

    if( $column_name == 'fv_user_lock_out' && $data = $this->is_user_locked_out( $user_id ) ) {
      $content = '<div data-fv_user_lock_out_unlock_wrap="'.$user_id.'">';
      $content .= '<abbr title="BusinessPress has detected '.$data['count'].' bad login attempts and has blocked further logis for this account.">Yes</abbr>';
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
    if( $last_lockout && ( strtotime($last_lockout) + WEEK_IN_SECONDS ) > time() ) {
      return;
    }
  
    update_user_meta( $user_id, 'fv_user_lockout_email', time() );
  
    $key = get_password_reset_key( $user );
  
    $message = __( 'Hi %1$s,

We have detected too many failed login attempts for your user account. If you had no login issues recently, then there might be automated login attempts targeted towards your user account.

Click here to re-enable login: %2$s

If you have a simple password it\'s possible that it was compromised. In that case click this link to set a more complex password: %3$s

Regards,
All at %4$s
%5$s' );

    wp_mail(
      $user->user_email,
      sprintf( __( '[%s] Failed login attempts detected' ), wp_specialchars_decode( get_option( 'blogname' ) ) ),
      sprintf(
        $message,
        $user->display_name,
        '<' . network_site_url( "wp-login.php?action=unlock&ukey=$key&login=" . rawurlencode( $user->user_login ), 'login' ) . ">",
        '<' . network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user->user_login ), 'login' ) . ">",
        wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ),
        home_url()
      )
    );
  }

  function login_form_unlock() {

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
  
    wp_redirect( add_query_arg( 'unlocked', true, wp_login_url() ) );
    exit;
  }

  function password_reset( $user ) {
    $this->remove_lock( $user->ID );
  }

  function prevent_authentication( $user_logged_in, $username, $password) {
    $user = get_user_by( 'login', $username );
    if( !$user ) {
      $user = get_user_by( 'email', $username );
    }

    if( $user ) {
      if( $this->is_user_locked_out( $user->ID ) ) {

        $this->lockout_email($user);

        return new WP_Error( 'authentication_failed', __( '<strong>Error</strong>: To many failed attempts.' ) );
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

  function wp_login_failed( $username ) {

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
  }

}

new FV_User_Lock_Out;