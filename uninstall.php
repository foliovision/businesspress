<?php

if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

if( is_multisite() ) {
  delete_site_option('businesspress');
  delete_site_option('businesspress_notices');
  delete_site_option('businesspress_core_update_delay');
} else {
  delete_option('businesspress');
  delete_option('businesspress_notices');
  delete_option('businesspress_core_update_delay');
}