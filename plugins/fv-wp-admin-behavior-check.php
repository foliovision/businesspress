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
	 * Behavior event type for suspend action.
	 */
	const TYPE_SUSPEND_APPLIED = 'suspend_applied';

	private $load_admin_styles = false;

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

		add_filter( 'businesspess_users_by_date_registered_user_table_row', array( $this, 'users_by_date_registered_user_table_row' ), 10, 2 );

		add_action( 'admin_footer', array( $this, 'admin_enqueue_scripts' ) );

		add_action( 'wp_ajax_businesspress_admin_behavior_check_unbanUser', array( $this, 'admin_ajax_unbanUser' ) );
	}

	public function admin_ajax_unbanUser() {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_send_json_error( 'You do not have permission to unban users.' );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		if ( ! $user_id ) {
			wp_send_json_error( 'User ID is required.' );
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'businesspress_admin_behavior_check_unbanUser' ) ) {
			wp_send_json_error( 'Nonce is invalid.' );
		}

		wp_update_user( array( 'ID' => $user_id, 'user_status' => 0 ) );

		if ( function_exists( 'SimpleLogger' ) ) {
			$user = get_user_by( 'ID', $user_id );

			SimpleLogger()->info(
				'FV WP Admin Behavior Check: Unbanned user #{user_id} {user_email}',
				array(
					'source'       => 'FV WP Admin Behavior Check',
					'user_id'      => $user_id,
					'user_email'   => $user->user_email,
					'_occasionsID' => 'fv_wp_admin_behavior_check:' . $user_id,
				)
			);
		}

		wp_send_json_success( 'User unbanned successfully.' );
	}

	public function admin_enqueue_scripts() {
		if ( $this->load_admin_styles ) {
			?>
			<style>
			.column-registered abbr {
				margin-right: .5em;
			}

			p.businesspress-admin-behavior-check-banned-label {
				background-color: #d00;
				color: white;
				display: inline-block;
				padding: 2px 4px;
				border-radius: 3px;
				font-size: 12px;
			}
			.businesspress-admin-behavior-user-list-wrap {
				visibility: hidden;
			}
			tr:hover .businesspress-admin-behavior-user-list-wrap {
				visibility: visible;
			}
			.businesspress-admin-behavior-check-banned-notice {
				background-color: #d00;
				color: white;
				padding: 10px;
				border-radius: 5px
			}
			.businesspress-admin-behavior-check-banned-notice a {
				color: white;
			}
			</style>
			<script>
			function businesspress_admin_behavior_check_unbanUser( user_id ) {
				foliopress_confirm(
					'Are you sure you want to unban this user?',
					function() {
						jQuery.post( ajaxurl, {
							action: 'businesspress_admin_behavior_check_unbanUser',
							nonce: '<?php echo wp_create_nonce( 'businesspress_admin_behavior_check_unbanUser' ); ?>',
							user_id: user_id
						}, function( response ) {
							if ( response.success ) {
								jQuery( "[data-businesspress-admin-behavior-check-user-id='" + user_id + "']" ).remove();
							} else {
								foliopress_confirm( response.data, function() {} );
							}
						});
					}
				);
			}
			</script>
			<?php

			wp_enqueue_style( 'wp-components' );
			wp_enqueue_script( 'foliopress-confirm', plugin_dir_url( dirname( __FILE__ ) ) . 'js/foliopress-confirm.js' );
		}
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

	public function get_simple_history_url( $user ) {
		return add_query_arg( array(
			'page' => 'simple_history_admin_menu_page',
			'users' => urlencode(
				wp_json_encode(
					array(
						array(
							'id' => (string) $user->ID,
							'value' => $user->display_name . ' (' . $user->user_email . ')'
						)
					)
				)
			),
			'context' => '_message_key:user_admin_page_access_denied'
		), admin_url( 'index.php' ) );
	}

	/**
	 * Create custom table for user behavior events.
	 *
	 * @return void
	 */
	public function maybe_create_table() {
		global $wpdb;

		$table_name = $wpdb->base_prefix . 'user_behavior';

		require_once constant( 'ABSPATH' ) . 'wp-admin/includes/upgrade.php';

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

		$verb = 'banned';
		$type = self::TYPE_BAN_APPLIED;

		/**
		 * We do not ban the user if he:
		 * - is not just subscriber
		 * - or he has or had a paid membership
		 * - or posted approved comments in the last month.
		 */
		if ( ! $this->maybe_ban_user_condition( $user ) ) {
			$verb = 'suspended';
			$type = self::TYPE_SUSPEND_APPLIED;

			/**
			 * Generate random password to avoid further logins.
			 */

			// Remove all profile_update actions
			remove_all_actions( 'profile_update' );

			// Do not send any emails about the password change.
			add_filter( 'send_password_change_email', '__return_false' );
			add_filter( 'send_email_change_email', '__return_false' );

			wp_update_user(
				array(
					'ID'            => $user_id,
					'user_pass'     => wp_hash_password( wp_generate_password( 8, false ) ),
				)
			);

			/**
			 * Send email to the user to let him know his account has been suspended with a link to reset the password.
			 */
			$subject = '[%s] Account suspended due to unusual activity';

			$message = "We have detected unusual activity in your account.\n\n";
			$message .= "We had to end your login sessions to avoid security risks.\n\n";
			$message .= "Please reset your password to retain access: %s\n\n";

			wp_mail(
				$user->user_email,
				sprintf( $subject, get_bloginfo( 'name' ) ),
				sprintf(
					$message,
					network_site_url( 'wp-login.php?action=rp&key=' . get_password_reset_key( $user ) . '&login=' . rawurlencode( $user->user_login ), 'login' )
				)
			);

		// Otherwise just ban the user, so he gets no chance of logging back in unless admin removes the ban.
		} else {
			wp_update_user(
				array(
					'ID'          => $user_id,
					'user_status' => self::STATUS_BANNED,
				)
			);
		}

		// End only the current active session.
		wp_destroy_current_session();
		wp_clear_auth_cookie();

		$this->insert_behavior_event(
			array(
				'user_id'        => $user_id,
				'blog_id'        => $blog_id,
				'screen'         => $screen,
				'type'           => $type,
				'request_uri'    => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
				'referrer'       => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
				'ip_address'     => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				'is_ban_trigger' => 1,
			)
		);

		if ( function_exists( 'SimpleLogger' ) ) {
			SimpleLogger()->warning(
				'FV WP Admin Behavior Check: User ' . $verb . ' after wp-admin access denied on multiple screens',
				array(
					'source'       => 'FV WP Admin Behavior Check',
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
	 * Check if the user meets the conditions to be banned.
	 *
	 * @param WP_User $user User object.
	 *
	 * @return bool True if the user meets the conditions to be banned. False otherwise.
	 */
	function maybe_ban_user_condition( $user ) {
		// Only subscribers are auto-banned. Other roles are still logged.

		// On Multisite
		if ( is_multisite() ) {

			$sites = get_sites();
			foreach( $sites AS $site ) {
				$user_on_blog = new WP_User( $user->ID, '', $site->blog_id );
				if ( ! empty( $user_on_blog->roles ) && ! in_array( 'subscriber', $user_on_blog->roles, true ) ) {
					return false;
				}
			}

		// Or on regular WordPress installation
		} else {
			if ( ! in_array( 'subscriber', (array) $user->roles, true ) ) {
				return false;
			}

		}

		// Skip current or past RCP members.
		if ( function_exists('rcp_get_customer_by_user_id') ) {
			$customer = rcp_get_customer_by_user_id( $user->ID );
			if ( $customer ) {
				$memberships = $customer->get_memberships();
				if ( $memberships ) {
					foreach ( $memberships as $membership ) {
						if ( in_array( $membership->get_status(), array( 'active', 'cancelled', 'expired' ) ) ) {
							return false;
						}
					}
				}
			}
		}

		// Skip user if he posted approved comments in the last month.

		// On Multisite
		if ( is_multisite() ) {

			$sites = get_sites();
			foreach( $sites AS $site ) {
				switch_to_blog( $site->blog_id );

				$has_approved_comments_in_last_month = $this->maybe_ban_user_condition_comments( $user->ID );
				if ( $has_approved_comments_in_last_month ) {
					restore_current_blog();
					return false;
				}

				restore_current_blog();
			}

		// Or on regular WordPress installation
		} else {
			$has_approved_comments_in_last_month = $this->maybe_ban_user_condition_comments( $user->ID );
			if ( $has_approved_comments_in_last_month ) {
				return false;
			}

		}

		return true;
	}

	/**
	 * Check if the user has more than 2 approved comments in the last month.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return bool True if the user posted more than 2 approved comments in the last month. False otherwise.
	 */
	function maybe_ban_user_condition_comments( $user_id ) {
		$approved_comments_count_in_last_month = get_comments(
			array(
				'user_id'    => $user_id,
				'type'       => 'comment',
				'status'     => 'approve',
				'count'      => true,
				'date_query' => array(
					array(
						'after' => '1 month ago',
					)
				),
			)
		);

		if ( $approved_comments_count_in_last_month > 2 ) {
			return true;
		}

		return false;
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

		$window_start = gmdate( 'Y-m-d H:i:s', time() - constant( 'DAY_IN_SECONDS' ) );

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
				$user_id = absint( $profile_user->ID );

				echo "<p class='businesspress-admin-behavior-check-banned-notice' data-businesspress-admin-behavior-check-user-id='" . $user_id . "'>";
				echo "Account banned by BusinessPress based on bad used activity in wp-admin.";

				if ( class_exists( 'SimpleLogger' ) ) {
					echo " <a href='" . $this->get_simple_history_url( $profile_user ) . "' target='_blank'>View bad actions</a>";
				}

				echo " <a href='javascript:void(0)' onclick='businesspress_admin_behavior_check_unbanUser(" . $user_id . ")'>Unban</a>";

				echo "</p>";

				$this->load_admin_styles = true;
			}
		}
	}

	public function users_by_date_registered_user_table_row( $html, $user ) {
		if ( 4 === absint( $user->user_status ) ) {
			$html .= '<p class="businesspress-admin-behavior-check-banned-label" data-businesspress-admin-behavior-check-user-id="' . $user->ID . '">Banned</p>';

			$html .= '<div class="businesspress-admin-behavior-user-list-wrap" data-businesspress-admin-behavior-check-user-id="' . $user->ID . '">';

			$links = array();
			if ( class_exists( 'SimpleLogger' ) ) {
				$links[] = '<a href="' . $this->get_simple_history_url( $user ) . '" target="_blank">View bad actions</a>';
			}
			$links[] = '<a href="javascript:void(0)" onclick="businesspress_admin_behavior_check_unbanUser(' . $user->ID . ')">Unban</a>';

			$html .= implode( ' | ', $links );

			$html .= '</div>';

			$this->load_admin_styles = true;
		}

		return $html;
	}

}

new FV_WP_Admin_Behavior_Check();
