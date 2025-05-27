<?php

class FV_Limit_Search {

  var $table_name;
  var $rate_limited = false;

  function __construct() {
    add_filter( 'pre_get_posts', array( $this, 'limit_search' ), 0 );
    add_action( 'init', array( $this, 'create_search_log_table' ) );

    global $wpdb;
    $this->table_name = $wpdb->prefix . 'businesspress_search_logs';
  }

  function create_search_log_table() {
    global $wpdb;

    if ( $wpdb->get_var( "SHOW TABLES LIKE '$this->table_name'" ) != $this->table_name ) {
      $charset_collate = $wpdb->get_charset_collate();
      
      $sql = "CREATE TABLE $this->table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        search_term varchar(255) NOT NULL,
        ip_address varchar(45) NOT NULL,
        created_at datetime NOT NULL,
        blocked tinyint(1) NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        KEY ip_address (ip_address),
        KEY created_at (created_at),
        KEY blocked (blocked)
      ) $charset_collate;";
      
      require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
      dbDelta( $sql );
    }
  }

  function limit_search( $query ) {
    if ( $query->is_main_query() && $query->is_search() ) {
      $search_term = get_search_query();

      global $businesspress;
      $ip_address = $businesspress->get_remote_addr();
      
      // Check rate limit
      if ( $this->is_rate_limited( $ip_address ) ) {
        // Log the blocked search
        $this->log_search( $search_term, $ip_address, true );

        // Nicer display of the error message if using "Enable Google style results" setting.
        if ( $businesspress->get_setting('search-results') ) {
          add_filter( 'pre_handle_404', array( $this, 'status_header' ) );

          // Block SearchWP 3 search query.
          add_filter( 'searchwp_short_circuit', '__return_true' );

          // Block core WordPress search query.
          add_filter( 'posts_search', array( $this, 'modify_search_query' ), 10, 2 );

          global $FV_Search;
          $FV_Search->rate_limited = true;
          return;

        } else {
          wp_die( 'Too many search requests. Please try again later.' );
        }
      }
      
      // Log the successful search
      $this->log_search( $search_term, $ip_address, false );
      
      // Clean up old logs
      $this->cleanup_old_logs();
    }
  }

  function is_rate_limited( $ip_address ) {
    global $wpdb;

    $one_minute_ago = date( 'Y-m-d H:i:s', strtotime( '-1 minute' ) );
    
    $count = $wpdb->get_var( $wpdb->prepare(
      "SELECT COUNT(*) FROM $this->table_name 
      WHERE ip_address = %s 
      AND blocked = 0
      AND created_at > %s",
      $ip_address,
      $one_minute_ago
    ) );
    
    return $count >= 7;
  }

  function log_search( $search_term, $ip_address, $blocked = false ) {
    global $wpdb;

    $wpdb->insert(
      $this->table_name,
      array(
        'search_term' => $search_term,
        'ip_address' => $ip_address,
        'created_at' => current_time( 'mysql', true ),
        'blocked' => $blocked ? 1 : 0
      ),
      array( '%s', '%s', '%s', '%d' )
    );
  }

  function cleanup_old_logs() {
    global $wpdb;

    $twenty_four_hours_ago = date( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );
    
    $wpdb->query( $wpdb->prepare(
      "DELETE FROM $this->table_name WHERE created_at < %s",
      $twenty_four_hours_ago
    ) );
  }

  function modify_search_query( $search, $wp_query ) {
    // This will block the SQL query with explanation of "Impossible WHERE"
    return ' AND 1=0';
  }

  public function status_header( $short_circuit ) {
    if ( ! headers_sent() ) {
      // Send our desired header
      header( 'HTTP/1.1 429 Too Many Requests' );

      // Short-circuit default header status handling.
      return true;
    }

    return false;
  }

}

new FV_Limit_Search();
