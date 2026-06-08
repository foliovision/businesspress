<?php

/**
 * All the functionality here was moved to the FV_Htaccess_Rules class.
 *
 * This class now only cleans-up the old .htaccess rules and stores 'anticlickjack_rewrite_cleaned_up' in the plugin
 * settings to avoid running the cleanup process multiple times.
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class FV_Clickjacking_Protection {

  public static function maybe_clean_up() {
    global $businesspress;

    if ( empty( $businesspress->aOptions['anticlickjack_rewrite_cleaned_up'] ) ) {
      self::clean_up();
    }
  }

  public static function clean_up() {
    global $businesspress, $wp_rewrite;

    if ( is_multisite() ) {
      return;
    }

    if( strpos( $_SERVER['SERVER_SOFTWARE'], 'Apache') === false) {
      return;
    }

    require_once constant('ABSPATH') . 'wp-admin/includes/file.php';
    require_once constant('ABSPATH') . 'wp-admin/includes/misc.php';

    $home_path     = get_home_path();
    $htaccess_file = $home_path . '.htaccess';

    $can_edit_htaccess = file_exists( $htaccess_file ) && is_writable( $home_path ) && $wp_rewrite->using_mod_rewrite_permalinks()
    || is_writable( $htaccess_file );

    if( $businesspress->get_setting( 'clickjacking-protection' ) && ! $businesspress->get_setting( 'anticlickjack_rewrite_cleaned_up' ) ) {
      if ( $can_edit_htaccess && got_mod_rewrite() ) {
        $rules = explode( "\n", $wp_rewrite->mod_rewrite_rules() );

        $businesspress->aOptions['anticlickjack_rewrite_cleaned_up'] = __('Success: Removed from .htaccess', 'businesspress');
        $businesspress->save_settings();
  
        insert_with_markers( $htaccess_file, 'WordPress', $rules );
      }
    }
  }
}

new FV_Clickjacking_Protection;