<?php

/**
 * When creating a new user, make sure the user_nicename is not just sanitized email address.
 *
 * WordPress sets user_nicename = sanitize_title( user_login ). When user_login
 * is an email address this produces a slug like "john-example-com", leaking the
 * email in author archive URLs. This plugin replaces it with just the local part
 * (or the display name when available).
 */

add_filter( 'wp_pre_insert_user_data', 'fv_safe_user_nicename', 10, 3 );

function fv_safe_user_nicename( $data, $update, $user_id ) {

	$nicename = $data['user_nicename'];
	$email    = isset( $data['user_email'] ) ? $data['user_email'] : '';

	if ( ! $email || $nicename !== sanitize_title( $email ) ) {
		return $data;
	}

	if ( ! empty( $data['display_name'] ) && $data['display_name'] !== $email ) {
		$new_nicename = sanitize_title( $data['display_name'] );
	} else {
		$local_part   = substr( $email, 0, strpos( $email, '@' ) );
		$new_nicename = sanitize_title( $local_part );
	}

	$new_nicename = substr( $new_nicename, 0, 50 );

	$data['user_nicename'] = fv_safe_user_nicename_unique( $new_nicename, $user_id );

	return $data;
}

function fv_safe_user_nicename_unique( $nicename, $user_id ) {
	global $wpdb;

	$original = $nicename;
	$suffix   = 2;

	while ( $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->users WHERE user_nicename = %s AND ID != %d", $nicename, $user_id ) ) ) {
		$nicename = substr( $original, 0, 50 - strlen( '-' . $suffix ) ) . '-' . $suffix;
		$suffix++;
	}

	return $nicename;
}
