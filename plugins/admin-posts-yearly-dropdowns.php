<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class BusinessPress_Admin_Posts_Yearly_Dropdowns {

  function __construct() {

    // Disable the standard dropdown for filtering items in the list table by month.
    add_filter( 'disable_months_dropdown', '__return_true' );

		// Tweak WP_Query for the "Last 12 months" and "All years" options.
    add_action( 'pre_get_posts', array( $this, 'query_last_12_years' ) );

		// Show "Select year" dropdown on top of post list tables.
    add_action( 'restrict_manage_posts', array( $this, 'years_dropdown' ) );
  }

	/**
	 * Tweak WP_Query for the "Last 12 months" and "All years" options.
	 *
	 * @param WP_Query $query The WP_Query instance (passed by reference).
	 */
  function query_last_12_years( $query ) {
    if ( ! empty( $query->query['year'] ) ) {
			// Deal with "Last 12 months" option.
			if ( 'last-12-months' === $query->query['year'] ) {
				$query->set( 'date_query', array(
					array(
						'after' => '12 months ago'
					)
				) );

			// Deal with the "All year" option.
			} else if ( 'all' === $query->query['year'] ) {
				$query->set( 'date_query', false );
			}
    }
  }

  /**
	 * Displays a dropdown for filtering items in the list table by year.
	 *
	 * Created from the core WordPress months_dropdown() function, but in our case:
	 * - we default to "Select year" instead of "All years"
	 * - "All years" has a value of "all"
	 * - we add "Last 12 years" too
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

		$year              = isset( $_GET['year'] ) ? (int) $_GET['year'] : 0;
    $is_last_12_months = isset( $_GET['year'] ) && 'last-12-months' === $_GET['year'];
		$is_all_years      = isset( $_GET['year'] ) && 'all' === $_GET['year'];
		?>
		<label for="filter-by-year" class="screen-reader-text"><?php echo get_post_type_object( $post_type )->labels->filter_by_date; ?></label>
		<select name="year" id="filter-by-year">
			<option<?php selected( $is_all_years ); ?> value=""><?php _e( 'Select year' ); ?></option>
			<option<?php selected( $is_all_years ); ?> value="all"><?php _e( 'All years' ); ?></option>
      <option<?php selected( $is_last_12_months ); ?> value="last-12-months"><?php _e( 'Last 12 months' ); ?></option>
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
		</select>
		<?php
	}
}

new BusinessPress_Admin_Posts_Yearly_Dropdowns();