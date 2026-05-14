<?php
/**
 * Original Plugin Name: Disable Embeds
 * Description: Don't like the enhanced embeds in WordPress 4.4? Easily disable the feature using this plugin. With Foliovision mods
 * Version:     1.3.0.fv
 * Author:      Pascal Birchler
 * Author URI:  https://pascalbirchler.com
 * License:     GPLv2+
 *
 * @package disable-embeds
 */

/**
 * Disable embeds on init.
 *
 * - Removes the needed query vars.
 * - Disables oEmbed discovery.
 * - Completely removes the related JavaScript.
 *
 * @since 1.0.0
 */
function disable_embeds_init() {
	global $businesspress;

	// Remove author information from oEmbed REST API response.
	add_filter( 'oembed_response_data', 'disable_embeds_oembed_response_data' );

	// If we are not disabling oEmbed, we don't need to do anything else.
	if ( ! $businesspress->get_setting( 'disable-oembed' ) ) {
		return;
	}

	// Remove the REST API endpoint.
	remove_action( 'rest_api_init', 'wp_oembed_register_route' );

	// Turn off oEmbed auto discovery.
	add_filter( 'embed_oembed_discover', '__return_false' );

	// Don't filter oEmbed results.
	remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );

	// Remove oEmbed discovery links.
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );

	// Remove oEmbed-specific JavaScript from the front-end and back-end.
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );
	add_filter( 'tiny_mce_plugins', 'disable_embeds_tiny_mce_plugin' );

	// Remove all embeds rewrite rules.
  /// Modification by Foliovision
	//add_filter( 'rewrite_rules_array', 'disable_embeds_rewrites' );

	// Remove filter of the oEmbed result before any HTTP requests are made.
	remove_filter( 'pre_oembed_result', 'wp_filter_pre_oembed_result', 10 );

	add_filter( 'template_redirect', array( 'disable_embeds_oembed_template' ) );
}

add_action( 'init', 'disable_embeds_init', 9999 );

/**
 * Remove author information from oEmbed REST API response.
 *
 * @param array $data The oEmbed response data.
 * @return array The modified oEmbed response data.
 */
function disable_embeds_oembed_response_data( $data ) {
	if ( isset( $data['author_name'] ) ) {
		unset( $data['author_name'] );
	}
	if ( isset( $data['author_url'] ) ) {
		unset( $data['author_url'] );
	}
	return $data;
}

/**
 * Removes the 'wpembed' TinyMCE plugin.
 *
 * @since 1.0.0
 *
 * @param array $plugins List of TinyMCE plugins.
 * @return array The modified list.
 */
function disable_embeds_tiny_mce_plugin( $plugins ) {
	return array_diff( $plugins, array( 'wpembed' ) );
}

/**
 * Remove all rewrite rules related to embeds.
 *
 * @since 1.2.0
 *
 * @param array $rules WordPress rewrite rules.
 * @return array Rewrite rules without embeds rules.
 */
function disable_embeds_rewrites( $rules ) {
	foreach ( $rules as $rule => $rewrite ) {
		if ( false !== strpos( $rewrite, 'embed=true' ) ) {
			unset( $rules[ $rule ] );
		}
	}

	return $rules;
}

function disable_embeds_oembed_template() {
	if( get_query_var( 'embed' ) ) {
		add_filter( 'template_include', '__return_false' );
	}
}

/**
 * Remove embeds rewrite rules on plugin activation.
 *
 * @since 1.2.0
 */
function disable_embeds_remove_rewrite_rules() {
	add_filter( 'rewrite_rules_array', 'disable_embeds_rewrites' );
	flush_rewrite_rules();
}
/// Modification by Foliovision
//register_activation_hook( __FILE__, 'disable_embeds_remove_rewrite_rules' );

/**
 * Flush rewrite rules on plugin deactivation.
 *
 * @since 1.2.0
 */
function disable_embeds_flush_rewrite_rules() {
	remove_filter( 'rewrite_rules_array', 'disable_embeds_rewrites' );
	flush_rewrite_rules();
}
/// Modification by Foliovision
//register_deactivation_hook( __FILE__, 'disable_embeds_flush_rewrite_rules' );