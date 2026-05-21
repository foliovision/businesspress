<?php

/**
 * Disable Application Passwords
 *
 * Prevents users below Administrator to set the application passwords.
 */
add_filter( 'wp_is_application_passwords_available_for_user', 'businesspress_wp_is_application_passwords_available_for_user', 10, 2 );

function businesspress_wp_is_application_passwords_available_for_user( $available, $user ) {
	if ( ! user_can( $user->ID, 'manage_options' ) ) {
		return false;
	}
	return $available;
}
