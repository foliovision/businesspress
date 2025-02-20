<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BusinessPress_Admin_Posts_Yearly_Dropdowns {

	const limit = 10;

	private $current_screen_months = array();

	function __construct() {

		// Check number of months and if it's high alter the main query for wp-admin -> Posts and other	post type screens.
		add_action( 'pre_get_posts', array( $this, 'query_last_12_years' ) );

		// Make sure the monthly dropdown is enabled as we might need it if there are not enough months.
		add_filter( 'disable_months_dropdown', '__return_false', PHP_INT_MAX );

		// Disable the standard dropdown for filtering items in the list table by month if there are more than 10 months.
		add_filter( 'pre_months_dropdown_query',  array( $this, 'maybe_disable_months_dropdown' ), 10, 2 );
	}

	function maybe_disable_months_dropdown( $months, $post_type ) {

		if ( isset( $this->current_screen_months[ $post_type ] ) ) {
			$months = $this->current_screen_months[ $post_type ];

			// More than 10 months? Show "Select year" dropdown on top of post list tables instead.
			if ( is_array( $months ) && count( $months ) > self::limit ) {
				add_action( 'restrict_manage_posts', array( $this, 'years_dropdown' ) );
				return array();
			}
		}

		return $months;
	}

	/**
	 * Check if there are more than 10 months.
	 * If so, tweak WP_Query for the "Last 12 months" and "All years" options.
	 *
	 * @param WP_Query $query The WP_Query instance (passed by reference).
	 */
	function query_last_12_years( $query ) {
		// Do not run if not in wp-admin, if POST request is being processed, or if not on wp-admin -> Posts kind of screen.
		if ( ! is_admin() || ! empty( $_POST ) || ! did_action( 'load-edit.php' ) || ! $query->is_main_query() ) {
			return;
		}

		$post_type = $query->query_vars['post_type'];

		if ( isset( $this->current_screen_months[ $post_type ] ) ) {
			$months = $this->current_screen_months[ $post_type ];

		} else {
			global $wpdb;

			/**
			 * Using code from WP_List_Table::months_dropdown() to get the months.
			 */
			$extra_checks = "AND post_status != 'auto-draft'";
			if ( ! isset( $_GET['post_status'] ) || 'trash' !== $_GET['post_status'] ) {
				$extra_checks .= " AND post_status != 'trash'";
			} elseif ( isset( $_GET['post_status'] ) ) {
				$extra_checks = $wpdb->prepare( ' AND post_status = %s', $_GET['post_status'] );
			}

			$months = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
					FROM $wpdb->posts
					WHERE post_type = %s
					$extra_checks
					ORDER BY post_date DESC",
					$post_type
				)
			);

			$this->current_screen_months[ $post_type ] = $months;
		}

		// Not enough months to bother with.
		if ( count( $months ) <= self::limit ) {
			return;
		}

		if ( ! empty( $query->query['year'] ) ) {
			// Deal with the "All years" option.
			if ( 'all' === $query->query['year'] ) {
				$query->set( 'date_query', false );
			}

		} else {
			$query->set( 'date_query', array(
				array(
					'after' => '12 months ago'
				)
			) );
		}
	}

	/**
	 * Displays a dropdown for filtering items in the list table by year.
	 *
	 * Created from the core WordPress months_dropdown() function, but in our case:
	 * - we default to "Last 12 years" instead of "All years"
	 * - "All years" has a value of "all"
	 *
	 * @global wpdb      $wpdb      WordPress database abstraction object.
	 *
	 * @param string $post_type The post type.
	 */
	public function years_dropdown( $post_type ) {
		global $wpdb;

		/**
		 * Filters whether to remove the 'Years' drop-down from the post list table.
		 *
		 * @param bool   $disable   Whether to disable the drop-down. Default false.
		 * @param string $post_type The post type.
		 */
		if ( apply_filters( 'disable_years_dropdown', false, $post_type ) ) {
			return;
		}

		/**
		 * Filters whether to short-circuit performing the years dropdown query.
		 *
		 * @param object[]|false $years   'Years' drop-down results. Default false.
		 * @param string         $post_type The post type.
		 */
		$years = apply_filters( 'pre_years_dropdown_query', false, $post_type );

		if ( ! is_array( $years ) ) {
			$extra_checks = "AND post_status != 'auto-draft'";
			if ( ! isset( $_GET['post_status'] ) || 'trash' !== $_GET['post_status'] ) {
				$extra_checks .= " AND post_status != 'trash'";
			} elseif ( isset( $_GET['post_status'] ) ) {
				$extra_checks = $wpdb->prepare( ' AND post_status = %s', $_GET['post_status'] );
			}

			$years = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DISTINCT YEAR( post_date ) AS year
					FROM $wpdb->posts
					WHERE post_type = %s
					$extra_checks
					ORDER BY post_date DESC",
					$post_type
				)
			);
		}

		/**
		 * Filters the 'Years' drop-down results.
		 *
		 * @param object[] $years    Array of the years drop-down query results.
		 * @param string   $post_type The post type.
		 */
		$years = apply_filters( 'years_dropdown_results', $years, $post_type );

		$year_count = count( $years );

		if ( ! $year_count || 1 === $year_count ) {
			return;
		}

		$year         = isset( $_GET['year'] ) ? (int) $_GET['year'] : 0;
		$is_all_years = isset( $_GET['year'] ) && 'all' === $_GET['year'];
		?>
		<label for="filter-by-year" class="screen-reader-text"><?php echo get_post_type_object( $post_type )->labels->filter_by_date; ?></label>
		<select name="year" id="filter-by-year">
			<option value=""><?php _e( 'Last 12 months' ); ?></option>
			<?php
			foreach ( $years as $arc_row ) {
				if ( 0 === (int) $arc_row->year ) {
					continue;
				}

				printf(
					"<option %s value='%s'>%s</option>\n",
					selected( $year, $arc_row->year, false ),
					esc_attr( $arc_row->year ),
					$arc_row->year
				);
			}
			?>
			<option<?php selected( $is_all_years ); ?> value="all"><?php _e( 'All years' ); ?></option>
		</select>
		<?php
	}
}

new BusinessPress_Admin_Posts_Yearly_Dropdowns();