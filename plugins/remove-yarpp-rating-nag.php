<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

add_action(
  'admin_notices',
  function() {
    if ( method_exists( 'YARPP_Admin', 'display_review_notice' ) ) {
      remove_action( 'admin_notices', array( 'YARPP_Admin', 'display_review_notice' ) );
    }
  },
  0
);