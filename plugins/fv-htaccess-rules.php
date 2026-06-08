<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FV_Htaccess_Rules {

	const MARKER = 'BusinessPress';

	const RULES = array(
		'clickjacking-protection'         => array(
			'<IfModule mod_headers.c>',
			'Header set X-Frame-Options "SAMEORIGIN"',
			'Header set Content-Security-Policy "frame-ancestors \'self\'"',
			'</IfModule>',
		),
	);

	function __construct() {
		add_action( 'wp', array( $this, 'anti_clickjacking_headers' ) );
	}

	/**
	 * Fallback if .htaccess is not writable
	 */
	function anti_clickjacking_headers() {
		global $businesspress;

		if (
			$businesspress->get_setting( 'clickjacking-protection' ) &&
			empty( get_query_var( 'fv_player_embed' ) ) &&
			! $businesspress->get_setting( 'anticlickjack_rewrite' )
		) {
			header( 'X-Frame-Options: SAMEORIGIN' );
			header( "Content-Security-Policy: frame-ancestors 'self'" );
		}
	}

	private static function set_rewrite_flags( $result ) {
		global $businesspress;

		foreach ( array_keys( self::RULES ) as $rule_key ) {
			$businesspress->aOptions[ $rule_key ] = $result && $businesspress->get_setting( $rule_key );
		}
	}

	private static function clear_rewrite_flags() {
		global $businesspress;

		foreach ( array_keys( self::RULES ) as $rule_key ) {
			$businesspress->aOptions[ $rule_key ] = false;
		}
	}

	/**
	 * Inserts rules at the top of .htaccess inside a marker block.
	 *
	 * @param string       $filename  Filename to alter.
	 * @param string       $marker    The marker name.
	 * @param array|string $insertion The new content to insert.
	 * @return bool True on write success, false on failure.
	 */
	private static function insert_at_top_with_markers( $filename, $marker, $insertion ) {
		if ( ! file_exists( $filename ) ) {
			if ( ! is_writable( dirname( $filename ) ) ) {
				return false;
			}

			if ( ! touch( $filename ) ) {
				return false;
			}

			$perms = fileperms( $filename );

			if ( $perms ) {
				chmod( $filename, $perms | 0644 );
			}
		} elseif ( ! is_writable( $filename ) ) {
			return false;
		}

		if ( ! is_array( $insertion ) ) {
			$insertion = explode( "\n", $insertion );
		}

		$instructions = sprintf(
			__(
				'Please use BusinessPress settings to enable or disable the following rules.
Any changes to the directives between these markers will be overwritten.', 'businesspress'
			),
		);

		$instructions = explode( "\n", $instructions );

		foreach ( $instructions as $line => $text ) {
			$instructions[ $line ] = '# ' . $text;
		}

		$insertion    = array_merge( $instructions, $insertion );
		$start_marker = "# BEGIN {$marker}";
		$end_marker   = "# END {$marker}";

		$fp = fopen( $filename, 'r+' );

		if ( ! $fp ) {
			return false;
		}

		flock( $fp, LOCK_EX );

		$lines = array();

		while ( ! feof( $fp ) ) {
			$lines[] = rtrim( fgets( $fp ), "\r\n" );
		}

		$rest_lines       = array();
		$found_marker     = false;
		$found_end_marker = false;

		foreach ( $lines as $line ) {
			if ( ! $found_marker && str_contains( $line, $start_marker ) ) {
				$found_marker = true;
				continue;
			} elseif ( ! $found_end_marker && str_contains( $line, $end_marker ) ) {
				$found_end_marker = true;
				continue;
			}

			if ( ! $found_marker || $found_end_marker ) {
				$rest_lines[] = $line;
			}
		}

		while ( ! empty( $rest_lines ) && '' === $rest_lines[0] ) {
			array_shift( $rest_lines );
		}

		$new_file_lines = array_merge(
			array( $start_marker ),
			$insertion,
			array( $end_marker )
		);

		if ( ! empty( $rest_lines ) ) {
			$new_file_lines[] = '';
			$new_file_lines   = array_merge( $new_file_lines, $rest_lines );
		}

		$new_file_data = implode( "\n", $new_file_lines );
		$old_file_data = implode( "\n", $lines );

		if ( $new_file_data === $old_file_data ) {
			flock( $fp, LOCK_UN );
			fclose( $fp );
			return true;
		}

		fseek( $fp, 0 );
		$bytes = fwrite( $fp, $new_file_data );

		if ( $bytes ) {
			ftruncate( $fp, ftell( $fp ) );
		}

		fflush( $fp );
		flock( $fp, LOCK_UN );
		fclose( $fp );

		return (bool) $bytes;
	}

	public static function maybe_process() {
		global $businesspress;

    $prevent_clickjacking_check = $businesspress->get_setting( 'htaccess_rules_timestamp' ) ? absint( $businesspress->get_setting( 'htaccess_rules_timestamp' ) ): 0;
    if ( $prevent_clickjacking_check < time() - 1 * constant('HOUR_IN_SECONDS') ) {
      self::process();

			$businesspress->aOptions['htaccess_rules_timestamp'] = time();
			$businesspress->save_settings();
		}
	}

	public static function process() {
		global $businesspress;

		if ( strpos( $_SERVER['SERVER_SOFTWARE'], 'Apache' ) === false ) {
			$businesspress->aOptions['htaccess_rules_result'] = __( 'Not using Apache, using header() fallback.', 'businesspress' );
			self::clear_rewrite_flags();
			$businesspress->save_settings();
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$home_path     = get_home_path();
		$htaccess_file = $home_path . '.htaccess';

		$can_edit_htaccess = ( file_exists( $htaccess_file ) && is_writable( $home_path ) )
			|| is_writable( $htaccess_file );

		if ( ! $can_edit_htaccess ) {
			$businesspress->aOptions['htaccess_rules_result'] = __( 'Error: .htaccess is not writable.', 'businesspress' );
			self::clear_rewrite_flags();
			$businesspress->save_settings();
			return;
		}

		$lines = array();

		foreach ( self::RULES as $key => $rules ) {
			if ( $businesspress->get_setting( $key ) ) {
				$lines = array_merge( $lines, $rules );
			}
		}

		$result = self::insert_at_top_with_markers( $htaccess_file, self::MARKER, $lines );

		self::set_rewrite_flags( $result );
		$businesspress->aOptions['htaccess_rules_result'] = $result
			? __( 'Success: .htaccess modified.', 'businesspress' )
			: __( 'Error: failed to modify .htaccess.', 'businesspress' );
		$businesspress->save_settings();
	}
}

new FV_Htaccess_Rules();
