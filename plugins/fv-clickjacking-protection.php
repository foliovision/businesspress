<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class FV_Clickjacking_Protection {

  function __construct() {
    add_action( 'wp', array( $this, 'anti_clickjacking_headers') );
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

  public static function maybe_process() {
    global $businesspress;

    $prevent_clickjacking_check = $businesspress->get_setting( 'anticlickjack_rewrite_timestamp' ) ? absint( $businesspress->get_setting( 'anticlickjack_rewrite_timestamp' ) ): 0;
    if ( $prevent_clickjacking_check < time() - 1 * HOUR_IN_SECONDS ) {
      self::process();

      $businesspress->aOptions['anticlickjack_rewrite_timestamp'] = time();
      $businesspress->save_settings();
    }
  }

  public static function process() {
    global $businesspress, $wp_rewrite;

    if ( is_multisite() ) {
      return;
    }

    if( strpos( $_SERVER['SERVER_SOFTWARE'], 'Apache') === false) {
      $businesspress->aOptions['anticlickjack_rewrite_result'] = __('Not using Apache, using header() fallback.', 'businesspress');
      $businesspress->save_settings();
      return;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/misc.php';

    $home_path     = get_home_path();
    $htaccess_file = $home_path . '.htaccess';

    $can_edit_htaccess = file_exists( $htaccess_file ) && is_writable( $home_path ) && $wp_rewrite->using_mod_rewrite_permalinks()
    || is_writable( $htaccess_file );

    $anti_clickjacking_rule = array(
      '# BEGIN Businesspress',
      '<IfModule mod_headers.c>',
      'Header set X-Frame-Options "SAMEORIGIN"',
      'Header set Content-Security-Policy "frame-ancestors \'self\'"',
      '</IfModule>',
      '# END Businesspress'
    );

    if( $businesspress->get_setting( 'clickjacking-protection' ) ) {
      if ( $can_edit_htaccess && got_mod_rewrite() ) {

        // Check if .htaccess already contains all anti-clickjacking rules
        $htaccess_content = file_exists( $htaccess_file ) ? file_get_contents( $htaccess_file ) : '';
        $all_rules_present = true;
        foreach( $anti_clickjacking_rule AS $rule_line ) {
          if ( strpos( $htaccess_content, $rule_line ) === false ) {
            $all_rules_present = false;
            break;
          }
        }

        if ( empty( $businesspress->get_setting( 'anticlickjack_rewrite' ) ) || ! $all_rules_present ) {
          $rules = explode( "\n", $wp_rewrite->mod_rewrite_rules() );
          $rules =  array_merge($rules, $anti_clickjacking_rule);
  
          $message = __('Error: failed to modify .htaccess.', 'businesspress');

          $result = insert_with_markers( $htaccess_file, 'WordPress', $rules );
          if ( $result ) {
            $message = __('Success: .htaccess modified.', 'businesspress');
          }

          $businesspress->aOptions['anticlickjack_rewrite'] = $result;
          $businesspress->aOptions['anticlickjack_rewrite_result'] = $message;

          // if ( $_SERVER['REMOTE_ADDR'] == '82.119.109.194' ) {
          //   var_dump( 'businesspress->aOptions anticlickjack_rewrite', $businesspress->aOptions['anticlickjack_rewrite'] );
          //   var_dump( 'businesspress->aOptions anticlickjack_rewrite_result', $businesspress->aOptions['anticlickjack_rewrite_result'] );
          // }

          $businesspress->save_settings();

          // if ( $_SERVER['REMOTE_ADDR'] == '82.119.109.194' ) {
          //   var_dump( 'businesspress->aOptions anticlickjack_rewrite', $businesspress->aOptions['anticlickjack_rewrite'] );
          //   var_dump( 'businesspress->aOptions anticlickjack_rewrite_result', $businesspress->aOptions['anticlickjack_rewrite_result'] );
          // }

          // if ( $_SERVER['REMOTE_ADDR'] == '82.119.109.194' ) {
          //   die( __CLASS__ . '::' . __FUNCTION__ . ':' . __LINE__ . "\n" );
          // }
        }

      } else {
        if(!$can_edit_htaccess) {
          $businesspress->aOptions['anticlickjack_rewrite_result'] = __('Error: .htaccess is not writable.', 'businesspress');
        } else {
          $businesspress->aOptions['anticlickjack_rewrite_result'] = __('Error: mod_rewrite is not loaded.', 'businesspress');
        }

        $businesspress->aOptions['anticlickjack_rewrite'] = false;
        $businesspress->save_settings();

      }

      // if ( $_SERVER['REMOTE_ADDR'] == '82.119.109.194' ) {
      //   die( __CLASS__ . '::' . __FUNCTION__ . ':' . __LINE__ . "\n" );
      // }

    } else if ( $businesspress->get_setting( 'anticlickjack_rewrite' ) ) {
      // if ( $_SERVER['REMOTE_ADDR'] == '82.119.109.194' ) {
      //   die( __CLASS__ . '::' . __FUNCTION__ . ':' . __LINE__ . "\n" );
      // }
      $rules = explode( "\n", $wp_rewrite->mod_rewrite_rules() );

      $businesspress->aOptions['anticlickjack_rewrite'] = false;
      $businesspress->aOptions['anticlickjack_rewrite_result'] = __('Success: Removed from .htaccess', 'businesspress');
      $businesspress->save_settings();

      insert_with_markers( $htaccess_file, 'WordPress', $rules );
    }
  }
}

new FV_Clickjacking_Protection;