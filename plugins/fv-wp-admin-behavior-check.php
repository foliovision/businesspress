<?php

/**
 * Ban users who repeatedly attempt forbidden wp-admin pages.
 *
 * @package BusinessPress
 */
class FV_WP_Admin_Behavior_Check {

	const STATUS_BANNED = 4;

	const ATTEMPT_LIMIT = 2;

	/**
	 * Behavior event type for denied admin page access.
	 */
	const TYPE_ADMIN_DENIED = 'admin_page_access_denied';

	/**
	 * Behavior event type for ban action.
	 */
	const TYPE_BAN_APPLIED = 'ban_applied';

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		// Allow wp_update_user() to store user_status.
		add_filter( 'wp_pre_insert_user_data', array( $this, 'allow_user_status_update' ), 10, 4 );

		// Create behavior table.
		add_action( 'admin_init', array( $this, 'maybe_create_table' ) );

		// Count denied wp-admin page access attempts.
		add_action( 'admin_page_access_denied', array( $this, 'register_denied_admin_access' ) );

		// Keep banned users from logging in.
		add_filter( 'wp_authenticate_user', array( $this, 'block_banned_users' ) );

		// Show ban status in wp-admin
		add_action( 'personal_options', array( $this, 'personal_options' ) );

		// Note: Ban status in wp-admin -> Users is shown using code in plugins/users-by-date-registered.php
	}

	/**
	 * Allow user_status to pass through wp_update_user().
	 *
	 * @param array $data     User data.
	 * @param bool  $update   Whether this is a user update.
	 * @param int   $user_id  User ID.
	 * @param array $userdata Original user data.
	 *
	 * @return array
	 */
	public function allow_user_status_update( $data, $update, $user_id, $userdata ) {
		if ( isset( $userdata['user_status'] ) ) {
			$data['user_status'] = absint( $userdata['user_status'] );
		}

		return $data;
	}

	/**
	 * Create custom table for user behavior events.
	 *
	 * @return void
	 */
	public function maybe_create_table() {
		global $wpdb;

		$table_name = $wpdb->base_prefix . 'user_behavior';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			blog_id bigint(20) unsigned NOT NULL DEFAULT 0,
			date datetime NOT NULL,
			screen varchar(191) NOT NULL DEFAULT '',
			type varchar(64) NOT NULL DEFAULT '',
			request_uri varchar(255) NOT NULL DEFAULT '',
			referrer text NOT NULL,
			ip_address varchar(45) NOT NULL DEFAULT '',
			is_ban_trigger tinyint(1) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY user_blog_date (user_id, blog_id, date),
			KEY user_blog_screen (user_id, blog_id, screen),
			KEY type_date (type, date)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Count denied admin page attempts and ban if threshold is reached.
	 *
	 * @return void
	 */
	public function register_denied_admin_access() {
		global $plugin_page, $typenow, $taxnow;

		$user = wp_get_current_user();

		if ( empty( $user ) || empty( $user->ID ) ) {
			return;
		}

		$user_id = absint( $user->ID );

		// Do nothing for already banned users.
		if ( self::STATUS_BANNED === intval( $user->user_status ) ) {
			return;
		}

		$blog_id = is_multisite() ? get_current_blog_id() : 0;
		$screen  = $this->get_denied_screen_key( $plugin_page, $typenow, $taxnow );

		$this->insert_behavior_event(
			array(
				'user_id'        => $user_id,
				'blog_id'        => $blog_id,
				'screen'         => $screen,
				'type'           => self::TYPE_ADMIN_DENIED,
				'request_uri'    => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
				'referrer'       => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
				'ip_address'     => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				'is_ban_trigger' => 0,
			)
		);

		$distinct_screens = $this->count_distinct_denied_screens( $user_id, $blog_id );

		if ( $distinct_screens < self::ATTEMPT_LIMIT ) {
			return;
		}

		$this->maybe_ban_user( $user, $blog_id, $screen, $distinct_screens );
	}

	/**
	 * Apply ban workflow for qualifying users.
	 *
	 * @param WP_User $user             User object.
	 * @param int     $blog_id          Blog ID.
	 * @param string  $screen           Denied screen key.
	 * @param int     $distinct_screens Number of denied screens.
	 *
	 * @return void
	 */
	private function maybe_ban_user( $user, $blog_id, $screen, $distinct_screens ) {
		$user_id = absint( $user->ID );

		// Only subscribers are auto-banned. Other roles are still logged.
		if ( ! in_array( 'subscriber', (array) $user->roles, true ) ) {
			return;
		}

		// Skip current or past RCP members.
		if ( function_exists('rcp_get_customer_by_user_id') ) {
			$customer = rcp_get_customer_by_user_id( $user_id );
			if ( $customer ) {
				$memberships = $customer->get_memberships();
				if ( $memberships ) {
					foreach ( $memberships as $membership ) {
						if ( in_array( $membership->get_status(), array( 'active', 'cancelled', 'expired' ) ) ) {
							return;
						}
					}
				}
			}
		}

		wp_update_user(
			array(
				'ID'          => $user_id,
				'user_status' => self::STATUS_BANNED,
			)
		);

		// End only the current active session.
		wp_destroy_current_session();
		wp_clear_auth_cookie();

		$this->insert_behavior_event(
			array(
				'user_id'        => $user_id,
				'blog_id'        => $blog_id,
				'screen'         => $screen,
				'type'           => self::TYPE_BAN_APPLIED,
				'request_uri'    => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
				'referrer'       => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
				'ip_address'     => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				'is_ban_trigger' => 1,
			)
		);

		if ( function_exists( 'SimpleLogger' ) ) {
			SimpleLogger()->warning(
				'FV WP Admin Behavior Check: Banned user #{user_id} after denied wp-admin access on multiple screens',
				array(
					'source'       => 'FV WP Admin Behavior Check',
					'user_id'      => $user_id,
					'user_email'   => $user->user_email,
					'blog_id'      => $blog_id,
					'screen'       => $screen,
					'attempts'     => $distinct_screens,
					'request_uri'  => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
					'referrer'     => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
					'remote_addr'  => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
					'_occasionsID' => 'fv_wp_admin_behavior_check:' . $user_id,
				)
			);
		}
	}

	/**
	 * Insert one behavior event row.
	 *
	 * @param array $data Event data.
	 *
	 * @return void
	 */
	private function insert_behavior_event( $data ) {
		global $wpdb;

		$table_name = $wpdb->base_prefix . 'user_behavior';

		$wpdb->insert(
			$table_name,
			array(
				'user_id'        => absint( $data['user_id'] ),
				'blog_id'        => absint( $data['blog_id'] ),
				'date'           => current_time( 'mysql', true ),
				'screen'         => sanitize_text_field( $data['screen'] ),
				'type'           => sanitize_key( $data['type'] ),
				'request_uri'    => sanitize_text_field( $data['request_uri'] ),
				'referrer'       => esc_url_raw( $data['referrer'] ),
				'ip_address'     => sanitize_text_field( $data['ip_address'] ),
				'is_ban_trigger' => absint( $data['is_ban_trigger'] ) ? 1 : 0,
			),
			array(
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
			)
		);
	}

	/**
	 * Count distinct denied screens for user on current blog.
	 *
	 * @param int $user_id User ID.
	 * @param int $blog_id Blog ID.
	 *
	 * @return int
	 */
	private function count_distinct_denied_screens( $user_id, $blog_id ) {
		global $wpdb;

		$table_name = $wpdb->base_prefix . 'user_behavior';

		$window_start = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT COUNT(DISTINCT screen) FROM {$table_name} WHERE user_id = %d AND blog_id = %d AND type = %s AND date >= %s";

		return absint(
			$wpdb->get_var(
				$wpdb->prepare(
					$sql,
					absint( $user_id ),
					absint( $blog_id ),
					self::TYPE_ADMIN_DENIED,
					$window_start
				)
			)
		);
	}

	/**
	 * Resolve denied screen key from globals available at deny point.
	 *
	 * @param string $plugin_page Plugin page slug.
	 * @param string $typenow     Post type.
	 * @param string $taxnow      Taxonomy.
	 *
	 * @return string
	 */
	private function get_denied_screen_key( $plugin_page, $typenow, $taxnow ) {
		if ( ! empty( $plugin_page ) ) {
			return 'plugin_page:' . sanitize_key( $plugin_page );
		}

		if ( ! empty( $typenow ) ) {
			return 'post_type:' . sanitize_key( $typenow );
		}

		if ( ! empty( $taxnow ) ) {
			return 'taxonomy:' . sanitize_key( $taxnow );
		}

		if ( ! empty( $_SERVER['SCRIPT_NAME'] ) ) {
			$script = wp_basename( wp_unslash( $_SERVER['SCRIPT_NAME'] ) );
			$script = sanitize_file_name( $script );
			if ( ! empty( $script ) ) {
				return $script;
			}
		}

		return 'unknown';
	}

	/**
	 * Prevent banned users from authenticating.
	 *
	 * @param WP_User|WP_Error $user User object or error.
	 *
	 * @return WP_User|WP_Error
	 */
	public function block_banned_users( $user ) {
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		if ( self::STATUS_BANNED === intval( $user->user_status ) ) {
			return new WP_Error(
				'fv_wp_admin_behavior_check_banned',
				'<strong>ERROR:</strong> This account has been banned.'
			);
		}

		return $user;
	}

  public function personal_options( $profile_user ) {
    if ( $profile_user && current_user_can( 'remove_users' ) ) {
      if ( 4 === absint( $profile_user->user_status ) ) {
        echo "<p style='background-color: #d00; color: white; padding: 10px; border-radius: 5px'>Account banned by BusinessPress based on bad used activity in wp-admin.</p>";
      }
    }
  }
}

new FV_WP_Admin_Behavior_Check();
