<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether login with email address should be enforced for this login attempt.
 *
 * Checks if "Require Email Address for Login" is set to "Yes, for all users" or "For Editors and Administrators".
 *
 * If it's "For Editors and Administrators", then it checks if the user is an editor or administrator.
 *
 * @param string $username Value submitted on login.
 * @return bool
 */
function fv_require_email_login_for_user_role_check( $username ) {
	global $businesspress;
	$mode = $businesspress->get_setting( 'login-email-address' );

  // "Yes, for all users" is set
	if ( 'all' === $mode ) {
		return true;
  
  // "For Editors and Administrators" is set
	} else if ( 'editors_administrators' === $mode ) {
    $user = get_user_by( 'login', $username );

    if ( ! $user && is_email( $username ) ) {
      $user = get_user_by( 'email', $username );
    }

    if ( ! $user ) {
      return false;
    }

    if ( is_multisite() ) {

      /**
       * Do a proper check of capabilities for each site of WordPress Multisite,
       * works even where user is not yet added to the site.
       * This only adds 1 query for each site it checks.
       */
      $sites = get_sites( array(
        'fields' => 'ids',
        'number' => 0, // all sites
      ) );

      foreach ( $sites as $site_id ) {
        $has_cap = user_can_for_site( $user->ID, $site_id, 'edit_others_posts' );
        if ( $has_cap ) {
          return true;
        }
      }

    } else {
      return user_can( $user, 'edit_posts' );
    }
  
  // "No" is set
  } else {
    return false;
  }
}

add_filter( 'authenticate', 'fv_require_email_address_for_login_but_do_not_tell', PHP_INT_MAX, 2 );

function fv_require_email_address_for_login_but_do_not_tell( $user, $username ) {
  global $businesspress;

	if ( ! fv_require_email_login_for_user_role_check( $username ) ) {
		return $user;
	}

  if( $username && !is_email( trim($username) ) ) {

    /**
     * If "For Editors and Administrators" is set, then we need to give them some feedback that they need to use their email address.
     *
     * We do not want to give a hint to the password crackers, so we at least do it in a way that is not easy to detect in HTML.
     */
    if ( 'editors_administrators' === $businesspress->get_setting( 'login-email-address' ) ) {

      // Core WordPress login form.
      add_action(
        'login_footer',
        function() {
          ?>
          <script>
          ( function() {
            document.getElementById('login_error').innerHTML = atob( '<?php echo base64_encode( wp_kses_post( __( '<strong>Error</strong>: Please use your e-mail address as the login user name.', 'businesspress' ) ) ); ?>' );
          } )();
          </script>
          <?php
        }
      );

      // No code needed for RCP as what's in fv_rcp_require_email() works.
    }

		return new WP_Error(
			'incorrect_password',
			sprintf(
				/* translators: %s: User name. */
				__( '<strong>Error:</strong> The password you entered for the username %s is incorrect.' ),
				'<strong>' . $username . '</strong>'
			) .
			' <a href="' . wp_lostpassword_url() . '">' .
			__( 'Lost your password?' ) .
			'</a>'
		);
  }

  return $user;
}

/*
 * Restrict Content Pro support
 */
add_action( 'rcp_login_form_errors', 'fv_rcp_require_email' );

function fv_rcp_require_email( $post ) {
  if ( ! fv_require_email_login_for_user_role_check( $_POST['rcp_user_login'] ) ) {
    return;
  }

  if( !empty($_POST['rcp_user_login']) && !is_email( $_POST['rcp_user_login'] ) ) {
    rcp_errors()->add( 'email_required', __( 'Please use your e-mail address as the login user name.' ), 'login' );
  }
}

/**
 * wp-login.php form
 */
add_action( 'login_footer', 'fv_require_email_address_for_login_script' );

function fv_require_email_address_for_login_script() {
  global $businesspress;
  if ( 'all' !== $businesspress->get_setting( 'login-email-address' ) ) {
    return;
  }

  // Do not run when resetting password
  if ( ! empty( $_GET['action'] ) && 'rp' === $_GET['action'] ) {
    return;
  }

  ?>
  <script>
  ( function() {
    let user_login = document.getElementById('user_login'),
      submit = document.getElementById('wp-submit');

    if ( user_login ) {
      user_login.type = 'email'
      submit.addEventListener( 'click', function() {
        let label = document.querySelector( 'label[for=user_login]' );
        if ( label ) {
          label.innerHTML = 'E-mail Address';
        }
      });
    }
  } )();
  </script>
  <?php
}

/**
 * Easy Digital Downloads
 */
add_filter( 'edd_login_form', 'fv_require_email_address_for_login_edd_script' );

function fv_require_email_address_for_login_edd_script( $html ) {
  global $businesspress;
  if ( 'all' !== $businesspress->get_setting( 'login-email-address' ) ) {
    return $html;
  }

  ob_start();
  ?>
  <script>
  ( function() {
    let notice_shown = false;

    let user_login = document.getElementById('edd_user_login'),
      submit = document.getElementById('edd_login_submit'),
      form = document.getElementById('edd_login_form');

    if ( submit ) {
      submit.addEventListener( 'mousedown', notice );
      submit.addEventListener( 'touchstart', notice );
    }

    if ( form ) {
      form.addEventListener( 'submit', function( e ) {
        if ( notice_shown ) {
          return;
        }

        e.preventDefault();
        e.stopPropagation();

        notice();
      } );
    }

    function notice() {
      user_login.type = 'email';

      let label = document.querySelector( 'label[for=edd_user_login]' );
      if ( label ) {
        label.innerHTML = 'E-mail Address';
      }

      notice_shown = true;
    }
  } )();
  </script>
  <?php
  $html .= "\n" . ob_get_clean();

  return $html;
}
