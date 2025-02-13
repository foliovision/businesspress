<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class BusinessPress_Admin_WooCommerce_Seach_Speed {

  function __construct() {

    /**
     * Stop WooCommerce Orders search as it does not respect the date range.
     */
    add_filter( 'woocommerce_shop_order_search_fields', function( $fields ) {
      return array();
    } );

    /**
     * Stop WooCommerce Subscriptions search as it does not respect the date range.
     */
    add_filter( 'woocommerce_shop_subscription_search_fields', function( $fields ) {
      return array();
    } );
  
    /**
     * Improve WooCommerce Orders search to consider the selected year.
     *
     * Works if BusinessPress 'Yearly dropdowns for posts filtering' setting is on.
     */
    add_filter(
      'woocommerce_shop_order_search_results',
      function( $order_ids, $term, $search_fields ) {
        return $this->search_results( 'shop_order', $term );
      },
      10,
      3
    );

    /**
     * Improve WooCommerce Subscriptions search to consider the selected year.
     *
     * Works if BusinessPress 'Yearly dropdowns for posts filtering' setting is on.
     */
    add_filter(
      'woocommerce_shop_subscription_search_results',
      function( $subscription_ids, $term, $search_fields ) {
        return $this->search_results( 'shop_subscriptiopn', $term );
      },
      10,
      3
    );

    /**
     * If we are at wp-admin -> WooCommerce -> Orders or Subscriptions and
     * BusinessPress 'Yearly dropdowns for posts filtering' setting is on,
     * we search in last year by default.
     *
     * We also give use an easy button to search in all years. 
     */
    if ( ! empty( $_GET['post_type'] ) && in_array( $_GET['post_type'], array( 'shop_order', 'shop_subscription' ) ) ) {
      add_action(
        'load-edit.php',
        function() {
          global $businesspress;

          // If BusinessPress 'Yearly dropdowns for posts filtering' is on
          if ( $businesspress->get_setting('admin-posts-yearly-dropdowns') ) {
            if ( ! empty( $_GET['s'] ) && empty( $_GET['year'] ) ) {
              $_GET['year'] = 'last-12-months';

              add_action( 'admin_footer', array( $this, 'search_notice' ) );
            }
          }
        }
      );
    }
  }

  function search_notice() {
    ?>
    <!-- BusinessPress_Admin_WooCommerce_Seach_Speed -->
    <script>
    jQuery( '.subtitle' ).append( ' <?php esc_html_e( 'Searching in last 12 months only', 'businesspress' ); ?> <button><?php esc_html_e( 'Search all years', 'businesspress' ); ?></button>' );
    jQuery( '.subtitle button' ).on( 'click', function() {
      jQuery( '#filter-by-year' ).val( 'all' );
      jQuery( '#posts-filter' ).submit();
    });
    </script>
    <?php
  }

  /**
   * Search for a term in $post_type.
   * 
   * This function is based on WC_Order_Data_Store_CPT::search_orders().
   *
   * @param string $post_type
   * @param string $term
   *
   * @return array
   */
  function search_results( $post_type, $term ) {
    global $wpdb;

    $search_fields = array( '_billing_email' );

    $date_query_meta = '';
    $date_query = '';

    if ( ! empty( $_GET['year'] ) ) {
      if ( 'all' === $_GET['year'] ) {

      } else if ( 'last-12-months' === $_GET['year'] ) {
        $date_query_meta = " AND p.post_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)";
        $date_query      = " AND p1.post_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)";

      } else {
        $date_query_meta = $wpdb->prepare( " AND DATE_FORMAT(p.post_date, '%%Y') = %s", $_GET['year'] );
        $date_query      = $wpdb->prepare( " AND DATE_FORMAT(p1.post_date, '%%Y') = %s", $_GET['year'] );
      }
    }

    $ids = array_unique( array_merge(
      $wpdb->get_col(
        $wpdb->prepare( "
          SELECT DISTINCT p1.post_id
          FROM {$wpdb->postmeta} p1
          JOIN {$wpdb->posts} p ON p1.post_id = p.ID
          WHERE p1.meta_value LIKE '%%%s%%'", $wpdb->esc_like( wc_clean( $term ) ) ) . " AND p1.meta_key IN ('" . implode( "','", array_map( 'esc_sql', $search_fields ) ) . "')" . $date_query_meta
      ),
      $wpdb->get_col(
        $wpdb->prepare( "
          SELECT p1.ID
          FROM {$wpdb->posts} p1
          INNER JOIN {$wpdb->postmeta} p2 ON p1.ID = p2.post_id
          INNER JOIN {$wpdb->users} u ON p2.meta_value = u.ID
          WHERE u.user_email LIKE '%%%s%%'
          AND p2.meta_key = '_customer_user'
          AND p1.post_type = %s
          " . $date_query,
          esc_attr( $term ),
          $post_type
        )
      )
    ) );

    return $ids;
  }

}

new BusinessPress_Admin_WooCommerce_Seach_Speed();