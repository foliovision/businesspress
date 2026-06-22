<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FV_Fail2ban {

	/**
	 * The instance of the FV_Fail2ban class.
	 *
	 * @var FV_Fail2ban
	 */
	static $instance = null;

  public static function _get_instance() {
    if( !self::$instance ) {
      self::$instance = new self();
    }

    return self::$instance;
  }

	public function __construct() {
		self::$instance = $this;

		add_action( 'template_redirect', array( $this, 'fail2ban_404' ) );
		add_action( 'wp_login_failed', array( $this, 'fail2ban_login' ) );
		add_filter( 'xmlrpc_login_error', array( $this, 'fail2ban_xmlrpc' ) );
		add_filter( 'xmlrpc_pingback_error', array( $this, 'fail2ban_xmlrpc_ping' ), 5 );
		add_action( 'lostpassword_post', array( $this, 'fail2ban_lostpassword' ) );

		/**
		 * Detect bad application passwords.
		 */

		// wp_authenticate_application_password() calls this for XMLRPC_REQUEST and REST_REQUEST if there are HTTP Authentication headers
		add_action( 'application_password_failed_authentication', array( $this, 'fail2ban_application_password_failed_authentication' ) );

		// ...however if no application passwords are set, wp_authenticate_application_password() would not trigger any error,
		// so we check on determine_current_user.
		add_action( 'determine_current_user', array( $this, 'fail2ban_application_password_not_enabled' ) );
	}

	function fail2ban_404() {
		if( preg_match( '~\.(bmp|css|eot|gif|ico|jpe|jpeg|jpg|js|m3u8|mp3|mp4|ogg|pdf|png|svg|tiff|ts|ttf|txt|vtt|webm|webp|woff|woff2)~i', $_SERVER['REQUEST_URI'] ) ) return;

		if( $_SERVER['REQUEST_URI'] == '/apple-app-site-association' || $_SERVER['REQUEST_URI'] == '/.well-known/apple-app-site-association' ) return;

		if( stripos($_SERVER['REQUEST_URI'], 'fv-gravatar-cache' ) !== false ) return;

		if( stripos($_SERVER['REQUEST_URI'], 'null' ) !== false ) return;

		if( ! empty( $_SERVER['HTTP_USER_AGENT'] ) && preg_match( '~(Mediapartners-Google|googlebot|bingbot)~i', $_SERVER['HTTP_USER_AGENT'] ) ) return;

		if( !is_404() || function_exists('bbp_is_single_user') && bbp_is_single_user() ) return;

		$this->write_to_log( 'fail2ban 404 error - '.$_SERVER['REQUEST_URI'] );
	}

	/**
	 * Detects if somebody is trying to use the incorrect application password.
	 * Flags the attempt to fail2ban for banning.
	 *
	 * @param WP_Error $error The authentication error.
	 */
	public function fail2ban_application_password_failed_authentication( $error ) {
		$this->write_to_log( 'fail2ban login error - Application password failed for ' . $_SERVER['PHP_AUTH_USER'] . ' at ' . $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Detects if somebody is trying to use the application password while it's not even enabled.
	 * Flags the attempt to fail2ban for banning and exits the script.
	 *
	 * @param int|false $input_user User ID if one has been determined, false otherwise.
	 * @return int|false The authenticated user ID if successful, false otherwise.
	 */
	public function fail2ban_application_password_not_enabled( $input_user ) {
		// Don't authenticate twice.
		if ( ! empty( $input_user ) ) {
			return $input_user;
		}

		if ( isset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ) ) {

			// Let WordPress handle the authentication if it's enabled.
			/**
			 * Note: This will return false until first user set the application password. Then core
			 * WordPress runs update_network_option( $network_id, self::OPTION_KEY_IN_USE, true ); to
			 * remember that an application password was EVER set up and only then it starts checking
			 * them... forever!
			 */
			if ( WP_Application_Passwords::is_in_use() ) {
				return $input_user;
			}

			// This condition is taken from wp-includes/user.php wp_authenticate_application_password() function.
			// The 'REST_REQUEST' check here may happen too early for the constant to be available.
			$is_api_request = ( ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) );

			if ( $is_api_request ) {
				$this->write_to_log( 'fail2ban login error - Application passwords not enabled for ' . $_SERVER['PHP_AUTH_USER'] . ' at ' . $_SERVER['REQUEST_URI'] );
			}
		}

		return $input_user;
	}

	/**
	 * Flag bad login attempt.
	 *
	 * @param string $username The username of the user attempting to login.
	 */
	function fail2ban_login( $username ) {
		$msg = "Authentication attempt for unknown user " . $username;

		if ( wp_cache_get( $username, 'userlogins' ) ) {
			$msg = "Authentication failure for " . $username;
		}

		// Do not ban users for failed login attempts via TR mobile app as they seem to occur too often due to bug in the mobile app.
		if ( ! wp_cache_get( $username, 'userlogins' ) && stripos( $_SERVER['REQUEST_URI'], '/generate_auth_cookie' ) !== false ) {
			$this->write_to_log( 'mobile app login error, not banning for now - '.$msg );
			return;
		}

		$this->write_to_log( 'fail2ban login error - '.$msg );
	}

	/**
	 * Flag bad lost password attempt.
	 *
	 * @param WP_Error $errors The errors object.
	 */
	function fail2ban_lostpassword( $errors ) {
		if ( $errors && method_exists( $errors, 'get_error_codes' ) && $errors->get_error_codes() ) {
			$user_login = ! empty( $_POST['user_login'] ) ? sanitize_text_field( $_POST['user_login'] ) : '?';
			$this->write_to_log( 'fail2ban login lostpassword error - user ' . $user_login . ' doesn\'t exists' );      
		}
	}  

	/**
	 * Flag bad XML-RPC login attempt.
	 *
	 * @param string $error The error message.
	 * @return string The error message.
	 */
	function fail2ban_xmlrpc( $error ) {
		$this->write_to_log( 'fail2ban login error - XML-RPC authentication failure' );
		return $error;
	}

	/**
	 * Flag bad XML-RPC pingback attempt.
	 *
	 * @param object $ixr_error The error object.
	 * @return object The error object.
	 */
	function fail2ban_xmlrpc_ping( $ixr_error ) {
		if( $ixr_error->code === 48 ) {
			return $ixr_error;
		}

		$this->write_to_log( 'fail2ban pingback error - XML-RPC Pingback error '.$ixr_error->code.' generated' );

		return $ixr_error;
	}

	/**
	 * Get the client IP address.
	 *
	 * Uses either just the REMOTE_ADDR
	 * or the HTTP_X_FORWARDED_FOR header if it exists and is valid
	 * and if either the X-Pull header is configured and the HTTP_X_PULL header matches.
	 * or if the REMOTE_ADDR is one of the known Cloudflare IPs.
	 *
	 * @return string The client IP address.
	 */
	public function get_remote_addr() {

		$sanitized_ip_from_x_forwarded_for = $this->get_remote_addr_from_x_forwarded_for();
		$x_pull_key = BusinessPress()->get_setting( 'xpull-key' );

		/**
		 * If X-Pull header is configured, we check the HTTP_X_PULL header to see if it matches.
		 * If it does we know we can trust the HTTP_X_FORWARDED_FOR header and get the client IP address.
		 */
		if ( $sanitized_ip_from_x_forwarded_for && $x_pull_key && ! empty( $_SERVER['HTTP_X_PULL'] ) && $_SERVER['HTTP_X_PULL'] == $x_pull_key ) {
			return $sanitized_ip_from_x_forwarded_for;
		}

		$aProxies = array();
		
		// https://www.cloudflare.com/ips-v4
		$aCloudFlareIP4 = "173.245.48.0/20
			103.21.244.0/22
			103.22.200.0/22
			103.31.4.0/22
			141.101.64.0/18
			108.162.192.0/18
			190.93.240.0/20
			188.114.96.0/20
			197.234.240.0/22
			198.41.128.0/17
			162.158.0.0/15
			104.16.0.0/13
			104.24.0.0/14
			172.64.0.0/13
			131.0.72.0/22";

		$aCloudFlareIP4 = array_map( 'trim', explode( "\n", $aCloudFlareIP4 ) );

		$aProxies = array_merge( $aProxies, $aCloudFlareIP4 );
		
		// https://www.cloudflare.com/ips-v6
		$aCloudFlareIP6 = "2400:cb00::/32
			2606:4700::/32
			2803:f800::/32
			2405:b500::/32
			2405:8100::/32
			2a06:98c0::/29
			2c0f:f248::/32";

		$aCloudFlareIP6 = array_map( 'trim', explode( "\n", $aCloudFlareIP6 ) );

		$aProxies = array_merge( $aProxies, $aCloudFlareIP6 );
		
		if ( defined( 'WP_FAIL2BAN_PROXIES' ) ) { //  todo: check this out      
			$aProxies = array_merge( $aProxies, explode( ',' ,WP_FAIL2BAN_PROXIES ) );
		}

		if ( $sanitized_ip_from_x_forwarded_for ) {
			$ip = ip2long($_SERVER['REMOTE_ADDR']);
			foreach( $aProxies as $proxy ) {
				if (2 == count($cidr = explode('/',$proxy))) {
					$net = ip2long($cidr[0]);
					$mask = ~ ( pow(2, (32 - $cidr[1])) - 1 );
				} else {
					$net = ip2long($proxy);
					$mask = -1;
				}
				if ($net == ($ip & $mask)) {
					return $sanitized_ip_from_x_forwarded_for;
				}
			}
		}

		return $_SERVER['REMOTE_ADDR'];    
	}

	/**
	 * Get the client IP address from the HTTP_X_FORWARDED_FOR header if it exists and is valid.
	 *
	 * Make sure you do not use the REMOTE_ADDR IP though. The last IP in the X-Forwarded-For header might be the CDN IP.
	 *
	 * @return string The client IP address.
	 */
	private function get_remote_addr_from_x_forwarded_for() {
		// Get the client IP from the HTTP_X_FORWARDED_FOR header if it exists and is valid.
		$sanitized_ip_from_x_forwarded_for = false;

		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {

			$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );

			$ips = array_map( 'trim', $ips );

			// Filter $ips with filter_var() to include only valid IPs
			$ips = array_filter( $ips, function( $ip ) {

				// Never use the CDN IP
				if ( $_SERVER['REMOTE_ADDR'] === $ip ) {
					return false;
				}

				return filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 );
			});

			$ips = array_values( $ips );

			if ( ! empty( $ips[0] ) ) {
				$sanitized_ip_from_x_forwarded_for = $ips[0];
			}
		}

		return $sanitized_ip_from_x_forwarded_for;
	}

	/**
	 * Write a message to the auth log for fail2ban to take action.
	 *
	 * @param string $message The message to write to the log. "BusinessPress " is prepended, and " from {IP}" is appended automatically.
	 * 
	 */
	public function write_to_log( $message ) {
		$host	= ! empty( $_ENV['WP_FAIL2BAN_HTTP_HOST'] ) ? $_ENV['WP_FAIL2BAN_HTTP_HOST'] : $_SERVER['HTTP_HOST'];

		openlog( "wordpress(" . $host . ")", LOG_NDELAY|LOG_PID, LOG_AUTH );

		syslog( LOG_INFO, 'BusinessPress ' . $message . ' from ' . $this->get_remote_addr() );
	}
}

function FV_Fail2ban() {
	return FV_Fail2ban::_get_instance();
}

FV_Fail2ban();
