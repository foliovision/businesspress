<?php
add_filter( 'authenticate', 'fv_require_email_address_for_login_but_do_not_tell', PHP_INT_MAX, 2 );

function fv_require_email_address_for_login_but_do_not_tell( $user, $username ) {

  if( $username && !is_email( trim($username) ) ) {
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
    // Is it using email? Then skip our check
    if( !empty($_POST['log']) && is_email( trim($_POST['log']) ) ) {
      remove_filter( 'authenticate', 'fv_require_email_address_for_login', PHP_INT_MAX, 2 ); 
    }
  }
}

/*
 * Restrict Content Pro support
 */
add_action( 'rcp_login_form_errors', 'fv_rcp_require_email' );

function fv_rcp_require_email( $post ) {
  remove_filter( 'authenticate', 'fv_require_email_address_for_login', PHP_INT_MAX, 2 );

  if( !empty($_POST['rcp_user_login']) && !is_email( $_POST['rcp_user_login'] ) ) {
    rcp_errors()->add( 'email_required', __( 'Please use your e-mail address as the login user name.' ), 'login' );
  }
}

/**
 * wp-login.php form
 */
add_action( 'login_footer', 'fv_require_email_address_for_login_script' );

function fv_require_email_address_for_login_script() {
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

  ob_start();
  ?>
  <script>
  ( function() {
    let user_login = document.getElementById('edd_user_login'),
      submit = document.getElementById('edd_login_submit');

    if ( submit ) {
      submit.addEventListener( 'mousedown', notice );
      submit.addEventListener( 'touchstart', notice );
    }

    function notice() {
      user_login.type = 'email';

      let label = document.querySelector( 'label[for=edd_user_login]' );
      if ( label ) {
        label.innerHTML = 'E-mail Address';
      }
    }
  } )();
  </script>
  <?php
  $html .= "\n" . ob_get_clean();

  return $html;
}
