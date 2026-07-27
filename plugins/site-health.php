<?php

/**
 * The default limit for wp_options size is just 800,000 B.
 * Almost any website is over that limit due to the way many plugins are coded.
 * So we set a more realistic expectation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'site_status_autoloaded_options_size_limit', 'businesspress_site_status_autoloaded_options_size_limit' );

function businesspress_site_status_autoloaded_options_size_limit( $limit ) {
	// Set threshold to 1.5 MB (1.5 * 1024 * 1024 bytes)
	return 1572864;
}