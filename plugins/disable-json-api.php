<?php
/**
 * Origin Plugin Name: Disable REST API
 * Plugin URI: http://www.binarytemplar.com/disable-json-api
 * Description: Disable the use of the JSON REST API on your website to anonymous users
 * Version: 1.3.2
 * Author: Dave McHale
 * Author URI: http://www.binarytemplar.com
 * License: GPL2+
 */

$dra_current_WP_version = get_bloginfo('version');

// Modern WordPress
if ( version_compare( $dra_current_WP_version, '4.7', '>=' ) ) {
    DRA_Force_Auth_Error();

// Legacy WordPress
} else {
    DRA_Disable_Via_Filters();
}


/**
 * This function is called if the current version of WordPress is 4.7 or above
 * Forcibly raise an authentication error to the REST API if the user is not logged in
 */
function DRA_Force_Auth_Error() {
    /// Addition by Foliovision
    remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
    add_filter( 'rest_authentication_errors', 'DRA_only_allow_logged_in_rest_access' );

    // Remove REST API endpoints from /wp-json/
    add_filter( 'rest_endpoints', 'DRA_remove_rest_endpoints' );
}

/**
 * This function gets called if the current version of WordPress is less than 4.7
 * We are able to make use of filters to actually disable the functionality entirely
 */
function DRA_Disable_Via_Filters() {
    
    if ( ! fv_DRA_is_allowed_endpoint() ) {
        // Filters for WP-API version 1.x
        add_filter( 'json_enabled', '__return_false' );
        add_filter( 'json_jsonp_enabled', '__return_false' );

        // Filters for WP-API version 2.x
        add_filter( 'rest_enabled', '__return_false' );
        add_filter( 'rest_jsonp_enabled', '__return_false' );
    }

    // Remove REST API info from head and headers
    remove_action( 'xmlrpc_rsd_apis', 'rest_output_rsd' );
    remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
    remove_action( 'template_redirect', 'rest_output_link_header', 11 );
}

/**
 * Returning an authentication error if a user who is not logged in tries to query the REST API
 * @param $access
 * @return WP_Error
 */
function DRA_only_allow_logged_in_rest_access( $access ) {

	if( ! fv_DRA_is_allowed_endpoint() && ! is_user_logged_in() ) {
        return new WP_Error( 'rest_cannot_access', __( 'Only authenticated users can access the REST API.', 'disable-json-api' ), array( 'status' => rest_authorization_required_code() ) );
    }

    return $access;
	
}

/**
 * Get the disallowed endpoints for non-logged in users.
 *
 * These are blocked for security reasons.
 *
 * @return array
 */
function DRA_get_disallowed_endpoints_for_non_logged_in_users() {
    return array(
        // Block user enumeration
        // Note: This does not only block wp/v2/users endpoint, but also any other endpoint that looks like a user endpoint
        '/users'
    );
}

/**
 * Checks if the URL looks like a WP REST API URL.
 *
 * If it does, it then checks if the endpoint is allowed:
 * - Easy Digital Downloads (EDD) webhooks
 * - oEmbed, if not disabled in BusinessPress
 *
 * @return boolean True if the endpoint is allowed or if it's not a WP REST API URL, false if it should be blocked.
 */
function fv_DRA_is_allowed_endpoint() {

    /**
     * Permit all endpoints if the user is logged in.
     * For example the block editor uses all kinds of REST API endpoints to get list of tags, users etc.
     */
    if ( is_user_logged_in() ) {
        return true;
    }

    /**
     * First check if it's a WP REST API URL at all.
     */

    // The URL path from web server, example: /wordpress-folder/wp-json/wp/v2/users or /wordpress-6.8/?rest_route=/wp/v2/users
    $request_url = sanitize_url( $_SERVER['REQUEST_URI'] );
    // The URL from the WP REST API, example: /wordpress-folder/wp-json/
    $rest_url    = wp_parse_url( get_rest_url(), PHP_URL_PATH );

    if (
        // This detection works for pretty permalinks
        stripos( $request_url, $rest_url ) === false &&
        // ...and this one for when using query strings
        empty( $_GET['rest_route'] )
    ) {
        // The page URL is not a WP REST API URL, so we stop checking
        return true;
    }

    global $businesspress;

    // REST API is disabled
    if ( $businesspress->get_setting( 'disable-rest-api' ) ) {
        /**
         * Even if "REST API" is disabled, we want to allow access to the following endpoints:
         * - EDD webhooks
         * - oEmbed, if not disabled in BusinessPress
         */
        $allowed_endpoints = array(
            '/edd/webhook'
        );

        if ( ! $businesspress->get_setting('disable-oembed' ) ) {
            $allowed_endpoints[] = '/oembed';
        }

        foreach( $allowed_endpoints as $path ) {
            if ( stripos( $request_url, $path ) !== false ) {
                return true;
            }
        }

        return false;

    // REST API is enabled
    } else {

        // Even if "REST API" is not disabled, we don't want non-logged in users to access disallowed endpoints
        foreach( DRA_get_disallowed_endpoints_for_non_logged_in_users() as $path ) {
            if ( stripos( $request_url, $path ) !== false ) {
                return false;
            }
        }

        return true;
    }
}

/**
 * Remove the REST API endpoints conditionally.
 * - If the user is logged in, we permit all endpoints to keep block editor working.
 * - If the user is not logged in, we remove the disallowed endpoints.
 *
 * @param array $endpoints The REST API endpoints.
 * @return array The modified REST API endpoints.
 */
function DRA_remove_rest_endpoints( $endpoints ) {

    /**
     * Permit all endpoints if the user is logged in.
     * For example the block editor uses all kinds of REST API endpoints to get list of tags, users etc.
     */
    if ( is_user_logged_in() ) {
        return $endpoints;

    /**
     * Even if "REST API" is enabled, we don't want non-logged in users to access "users" endpoint
     */
    } else {
        // Remove all disallowed endpoints from /wp-json/
        foreach( $endpoints as $key => $endpoint ) {
            foreach( DRA_get_disallowed_endpoints_for_non_logged_in_users() as $path ) {
                if ( stripos( $key, $path ) !== false ) {
                    unset( $endpoints[ $key ] );
                }
            }
        }
        return $endpoints;
    }
}
