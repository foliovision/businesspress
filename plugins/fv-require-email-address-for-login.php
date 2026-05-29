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

/*
 * Restrict Content Pro support
 */
add_action( 'rcp_login_form_errors', 'fv_rcp_require_email' );

function fv_rcp_require_email( $post ) {
  if( !empty($_POST['rcp_user_login']) && !is_email( $_POST['rcp_user_login'] ) ) {
    rcp_errors()->add( 'email_required', __( 'Please use your e-mail address as the login user name.' ), 'login' );
  }
}

/**
 * wp-login.php form
 */
add_action( 'login_footer', 'fv_require_email_address_for_login_script' );

function fv_require_email_address_for_login_script() {

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
