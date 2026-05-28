<?php

/**
 * Remove the user_nicename from the comment classes.
 * This is used to avoid user enumeration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function fv_remove_user_nicename_comment_class( $classes ) {
	foreach( $classes as $key => $class ) {
		if ( strpos( $class, 'comment-author-' ) !== false ) {
			unset( $classes[ $key ] );
		}
	}
	return $classes;
}

add_filter( 'comment_class', 'fv_remove_user_nicename_comment_class', 999 );
