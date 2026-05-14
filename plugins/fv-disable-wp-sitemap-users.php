<?php
/**
 * Disable the users sitemap as it makes it very easy to enumerate users.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'wp_sitemaps_add_provider', 'fv_disable_wp_sitemap_users', 10, 2 );

function fv_disable_wp_sitemap_users( $provider, $name ) {
	if ( 'users' === $name ) {
		return false;
	}
	return $provider;
}
