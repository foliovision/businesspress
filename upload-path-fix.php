<?php

/*
Since October 2019 WordPress doesn't support ../ in the upload paths: https://core.trac.wordpress.org/changeset/46476

Here is a hotfix:
*/

add_filter( 'upload_dir', 'businesspress_fix_relative_paths' );

function businesspress_fix_relative_paths( $uploads ) {
  $site_url = parse_url(site_url());  // Example: https://example.com/wordpress/
  $home_url = parse_url(home_url());  // Example: https://example.com/
  
  // if WordPress runs in a folder but the homepage is the web root
  if( !empty($site_url['path']) && empty($home_url['path']) ) {
    $wp_folder = $site_url['path']; // Example: /wordpress
    
    $uploads['path'] = str_replace($wp_folder.'/..','',$uploads['path']);
    $uploads['basedir'] = str_replace($wp_folder.'/..','',$uploads['basedir']);
    
  }
  
  return $uploads;
}
