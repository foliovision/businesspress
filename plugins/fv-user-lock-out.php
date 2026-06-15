<?php

class FV_User_Lock_Out {

	const META_LOCKED           = '_fv_account_locked';
	const META_COUNT            = '_fv_bad_logins_count';
	const META_LAST             = '_fv_bad_logins_last';
	const META_LOCKOUT_EMAIL    = 'fv_user_lockout_email';
	const LOCK_THRESHOLD        = 20;
	const CRON_HOOK             = 'businesspress_fv_user_lock_out_decay_counts';
	const DECAY_BATCH_SIZE      = 500;

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

		add_action( 'init', array( $this, 'maybe_schedule_decay_cron' ) );
		add_action( self::CRON_HOOK, array( $this, 'decay_bad_login_counts' ) );
	}

	/**
	 * Schedule weekly decay of stored failure counts (not the lock flag).
	 */
	function maybe_schedule_decay_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'weekly', self::CRON_HOOK );
		}
	}

	/**
	 * Halve failure counts for idle accounts that are not actively locked out.
	 */
	function decay_bad_login_counts() {
		$paged = 1;

		do {
			$query = new WP_User_Query(
				array(
					'fields'     => 'ID',
					'number'     => self::DECAY_BATCH_SIZE,
					'paged'      => $paged,
					'meta_query' => array(
						array(
							'key'     => self::META_COUNT,
							'value'   => 0,
							'compare' => '>',
							'type'    => 'NUMERIC',
						),
					),
				)
			);

			$user_ids = $query->get_results();

			if ( empty( $user_ids ) ) {
				break;
			}

			foreach ( $user_ids as $user_id ) {
				$this->maybe_decay_user_count( (int) $user_id );
			}

			$paged++;
		} while ( count( $user_ids ) === self::DECAY_BATCH_SIZE );
	}

	/**
	 * @param int $user_id User ID.
	 */
	function maybe_decay_user_count( $user_id ) {
		if ( $this->is_user_locked_out( $user_id ) ) {
			return;
		}

		$last = (int) get_user_meta( $user_id, self::META_LAST, true );
		if ( $last && ( $last + constant( 'DAY_IN_SECONDS' ) ) > time() ) {
			return;
		}

		$count = (int) get_user_meta( $user_id, self::META_COUNT, true );
		if ( $count < 1 ) {
			return;
		}

		$new_count = (int) floor( $count / 2 );

		if ( $new_count < 1 ) {
			delete_user_meta( $user_id, self::META_COUNT );
		} else {
			update_user_meta( $user_id, self::META_COUNT, $new_count );
		}
  }

  function admin_ajax() {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_send_json( array( 'error' => __( 'You do not have permission to unlock users.', 'businesspress' ) ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		if ( ! $user_id || ! wp_verify_nonce( $_POST['nonce'], 'fv_user_lock_out_unlock-' . $user_id ) ) {
      wp_send_json( array( 'error' => 'Nonce error.' ) );
    }
    
		$this->remove_lock( $user_id );

		if( function_exists("SimpleLogger") ) {
			$user = get_user( $user_id );
      SimpleLogger()->info( 'Admin unlocked locked out user account for: ' . $user->user_email );
    }

    wp_send_json( array( 'message' => 'Done, user will be able to log in again.' ) );
  }

  function admin_column( $columns ) {
		$columns['fv_user_lock_out'] = "Locked Out?";
    return $columns;
  }

  function admin_column_content( $content, $column_name, $user_id ) {

		if ( $column_name === 'fv_user_lock_out' && $data = $this->is_user_locked_out( $user_id ) ) {
			$content  = '<div data-fv_user_lock_out_unlock_wrap="' . esc_attr( $user_id ) . '">';
			$content .= '<span title="BusinessPress has detected ' . esc_attr( $data['count'] ) . ' bad login attempts and has blocked further logins for this account." style="display: inline-flex; color: #fff; border-radius: 3px; line-height: 30px; padding: 0 0.75rem; margin-right: 0.35rem; background: #AF4c50">Locked Out</span>';
			$content .= '<div class="row-actions"><a href="#" data-fv_user_lock_out_unlock="' . esc_attr( $user_id ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'fv_user_lock_out_unlock-' . $user_id ) ) . '">Unlock</a></div>';
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

	/**
	 * @param int $user_id User ID.
	 * @return array|false Lock data for UI, or false.
	 */
  function is_user_locked_out( $user_id ) {
    $locked = get_user_meta( $user_id, self::META_LOCKED, true );
    if( $locked ) {
      return array(
        'count' => (int) get_user_meta( $user_id, self::META_COUNT, true ),
        'time'  => (int) get_user_meta( $user_id, self::META_LAST, true ),
      );
    }

    // Migrate accounts that were locked under the old count + 24h rule.
		$count = (int) get_user_meta( $user_id, self::META_COUNT, true );
		$time  = (int) get_user_meta( $user_id, self::META_LAST, true );

		if ( $count > self::LOCK_THRESHOLD && $time && ( $time + constant( 'DAY_IN_SECONDS' ) ) > time() ) {
			update_user_meta( $user_id, self::META_LOCKED, $time );
			return array(
        'count' => $count,
        'time' => $time
      );
    }

    return false;
  }

  function lockout_email( $user ) {
    $user_id = $user->ID;
  
		$last_lockout = get_user_meta( $user_id, self::META_LOCKOUT_EMAIL, true );
  
		// Only send email once per week
		if( $last_lockout && ( $last_lockout + constant( 'WEEK_IN_SECONDS' ) ) > time() ) {
      return;
    }
  
		update_user_meta( $user_id, self::META_LOCKOUT_EMAIL, time() );
  
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
			<a class="button" href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Password Reset' ); ?></a>
      <?php
      login_footer();
      exit;
    }
  
		$user = check_password_reset_key( wp_unslash( $_GET['ukey'] ), wp_unslash( $_GET['login'] ) );
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
      $expiration_duration = 3 * constant( 'DAY_IN_SECONDS' );
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

        // user_status is 0 if the user is active, otherwise do not send the email
        if ( 0 === intval( $user->user_status ) ) {
          $this->lockout_email($user);
        }

        return new WP_Error( 'authentication_failed', __( '<strong>Error</strong>: Too many failed attempts. Please check your email to log in again.' ) );
      }
    }

    return $user_logged_in;
  }

	/**
	 * Clear lock flag and failure counters; backup previous values.
	 *
	 * @param int $user_id User ID.
	 */
  function remove_lock( $user_id ) {
		foreach ( array(
			self::META_LOCKED,
			self::META_LAST,
			self::META_COUNT,
			self::META_LOCKOUT_EMAIL,
		) as $meta_key ) {
      $meta_value = get_user_meta( $user_id, $meta_key, true );
      delete_user_meta( $user_id, $meta_key );
			update_user_meta( $user_id, $meta_key . '_previous', $meta_value );
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
   * Beagle Security scanner IPs.
   *
   * @return array
   */
  function get_beagle_security_ips() {
    return array(
      '61.2.45.236',
      '202.191.67.66',
      '40.160.7.58',
      '40.160.7.50',
      '54.91.159.245',
      '35.171.183.143',
      '44.203.241.170',
      '44.203.21.113',
      '15.204.216.152',
      '40.160.7.108',
      '40.160.7.113',
      '40.160.6.71',
    );
  }

  /**
   * Whether the current request is from a Beagle Security scanner IP.
   *
   * Uses REMOTE_ADDR only so client-supplied proxy headers cannot be spoofed.
   *
   * @return bool
   */
  function is_beagle_security_ip() {
    if ( ! isset( $_SERVER['REMOTE_ADDR'] ) ) {
      return false;
    }

    $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );

    return in_array( $ip, $this->get_beagle_security_ips(), true );
  }

  /**
   * Record number of bad login attempts and last time of bad login attempt.
   * Not if user just provided the username while the email address is required.
   * 
	 * @param string       $username Username.
	 * @param WP_Error|bool $error   Error object (WordPress 5.4+).
   */
  function wp_login_failed( $username, $error = false ) {

    // Ignore if using "Require Email Address for Login"
    if( $error && $error->get_error_code() == 'email_required' ) {
      return;
    }

    // Ignore Beagle Security scanner failed login attempts
    if( $this->is_beagle_security_ip() ) {
      return;
    }

    // If using WordPress before 5.4, we need to check if $_POST['log'] is not an email address
    $login = isset( $_POST['log'] ) ? wp_unslash( $_POST['log'] ) : '';

    if ( ! is_email( $login ) ) {
      if (
        function_exists( 'fv_require_email_login_for_user_role_check' ) &&
        fv_require_email_login_for_user_role_check( $login )
      ) {
        return;
      }
    }

    $user = get_user_by( 'login', $username );
    if( !$user ) {
      $user = get_user_by( 'email', $username );
    }

    if( $user ) {
      $count = get_user_meta( $user->ID, self::META_COUNT, true );
      if( !$count ) {
        $count = 0;
      }

      $count++;

      update_user_meta( $user->ID, self::META_COUNT, $count );
      update_user_meta( $user->ID, self::META_LAST, time() );

      // user_status is 0 if the user is active, otherwise do not send the email
      if ( $this->is_user_locked_out( $user->ID ) && 0 === intval( $user->user_status ) ) {
        update_user_meta( $user->ID, self::META_LOCKED, time() );

        $this->lockout_email($user);
      }
    }
  }

}

new FV_User_Lock_Out();

