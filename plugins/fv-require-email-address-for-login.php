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