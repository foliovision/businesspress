<?php

/**
 * Remove the user_nicename from the comment classes.
 * This is used to avoid user enumeration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function fv_remove_user_nicename_comment_class( $classes, $css_class, $comment_ID, $comment, $post ) {
	foreach( $classes as $key => $class ) {
		if ( strpos( $class, 'comment-author-' ) !== false ) {
			$classes[ $key ] = 'comment-author-' . fv_remove_user_nicename_comment_class_hash( $comment->user_id );
		}
	}
	return $classes;
}

add_filter( 'comment_class', 'fv_remove_user_nicename_comment_class', 999, 5 );

/**
 * Generate a hash of the user_id for the comment classes.
 * This is used to avoid user enumeration.
 *
 * Used in FV Cache for Users, must remain accessible from within $businesspress->load_extensions()!
 *
 * @param int $user_id The user ID.
 * @return string The hash of the user_id.
 */
function fv_remove_user_nicename_comment_class_hash( $user_id ) {
	static $cache = array();
	if( isset( $cache[ $user_id ] ) ) {
		return $cache[ $user_id ];
	}
	$cache[ $user_id ] = substr( hash_hmac( 'sha256', (string) $user_id, constant('NONCE_SALT') ), 0, 12 );
	return $cache[ $user_id ];
}