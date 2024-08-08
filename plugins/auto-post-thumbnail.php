<?php

/*
Original Plugin Name: Auto Post Thumbnail
Plugin URI: http://www.sanisoft.com/blog/2010/04/19/wordpress-plugin-automatic-post-thumbnail/
Description: Automatically generate the Post Thumbnail (Featured Thumbnail) from the first image in post (or any custom post type) only if Post Thumbnail is not set manually.
Version: 100.3.4.1.fv
Author: Aditya Mooley <adityamooley@sanisoft.com>, Tarique Sani <tarique@sanisoft.com>
Author URI: http://www.sanisoft.com/blog/author/adityamooley/
Modified by Dr. Tarique Sani <tarique@sanisoft.com> to make it work with Wordpress 3.4
*/

/*  Copyright 2009  Aditya Mooley  (email : adityamooley@sanisoft.com)
  
  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.
  
  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.
  
  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

add_action('publish_post', 'apt_publish_post');

// This hook will now handle all sort publishing including posts, custom types, scheduled posts, etc.
add_action('transition_post_status', 'apt_check_required_transition', 10, 3);


/**
 * Function to check whether scheduled post is being published. If so, apt_publish_post should be called.
 *
 * @param $new_status
 * @param $old_status
 * @param $post
 * @return void
 */
function apt_check_required_transition( $new_status, $old_status, $post ) {
  if ('publish' == $new_status) {
    apt_publish_post($post->ID);
  }
}

/**
 * Function to save first image in post as post thumbmail.
 * 
 * Runs if the featured image is not already set.
 */
function apt_publish_post($post_id)
{

  $did_set_featured_image = false;
  $did_set_social_warfare_image = false;

  /**
   * Only set featured image if user is actually editing a post.
   * 
   * This excludes Quick Edit and Bulk actions.
   */
  global $wp;
  $is_classic_editor_save = !empty($_POST['action']) && $_POST['action'] === 'editpost' && !empty($_POST['post_ID']) && $_POST['post_ID'] == $post_id;
  $is_gutenberg_post_save = !empty($wp->query_vars['rest_route']) && $wp->query_vars['rest_route'] == '/wp/v2/posts/'.$post_id;

  if ( ! $is_classic_editor_save && ! $is_gutenberg_post_save ) {
    return;
  }

  global $wpdb;
  
  // First check whether Post Thumbnail is already set for this post.
  if (get_post_meta($post_id, '_thumbnail_id', true) || get_post_meta($post_id, 'skip_post_thumb', true)) {
    return;
  }
  
  $post = $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE id = $post_id");
  
  // Initialize variable used to store list of matched images as per provided regular expression
  $matches = array();
  
  // Get all images from post's body
  preg_match_all('/<\s*a [^\>]*href\s*=\s*[\""\']?([^\""\'>]*)[\s\S]*?<\/a>/i', $post[0]->post_content, $matches_a);
  
  foreach( $matches_a[1] AS $k => $v ) {
    if( !preg_match( '~\.(jpg|gif|png|jpeg|jpe)$~i', $v ) ) {
      unset($matches_a[1][$k]);
      unset($matches_a[0][$k]);
    }
  }
  
  /**
   * Match the entire image tag and remember the src attribute (including quotes, these are stripped later)
   * We need the full tag to be able to check its class to find the thumbnail id, like:
   * 
   * <img src="..." alt="" class="wp-image-65"/>
   */
  preg_match_all('/<\s*img [^\>]*src\s*=\s*[\""\']?([^\""\'>]*).*?>/i', $post[0]->post_content, $matches_img);
  
  $matches = array( array_merge($matches_a[0],$matches_img[0]), array_merge($matches_a[1],$matches_img[1]) );
  
  if (count($matches)) {
    foreach ($matches[0] as $key => $image) {
      /**
      * If the image is from wordpress's own media gallery, then it appends the thumbmail id to a css class.
      * Look for this id in the IMG tag.
      */
      preg_match('/wp-image-([\d]*)/i', $image, $thumb_id);
      if($thumb_id){
        $thumb_id = $thumb_id[1];
      } else {
        $thumb_id = false;
      }

      // If thumb id is not found, try to look for the image in DB. Thanks to "Erwin Vrolijk" for providing this code.
      if (!$thumb_id) {
        $image = substr($image, strpos($image, '"')+1);
        $result = $wpdb->get_results("SELECT ID FROM {$wpdb->posts} WHERE guid = '".$matches[1][$key]."'");
        if($result){
          $thumb_id = $result[0]->ID;
        } else {  //  another try to look for the image using the media library meta data
          $aURL = parse_url($matches[1][$key]);
          $aUploadsInfo = wp_upload_dir();
          $aUploads = parse_url($aUploadsInfo['baseurl']);
          $thumb_id = $wpdb->get_var("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_value = '".str_replace( trailingslashit($aUploads['path']),'', $aURL['path'] )."'");     
        }
      
      }
      
      // Ok. Still no id found. Some other way used to insert the image in post. Now we must fetch the image from URL and do the needful.
      if (!$thumb_id) {
        $thumb_id = apt_generate_post_thumb($matches, $key, $post[0]->post_content, $post_id);
      }
      
      // If we succeed in generating thumg, let's update post meta
      if ($thumb_id) {
        update_post_meta( $post_id, '_thumbnail_id', $thumb_id );

        $did_set_featured_image = true;

        /**
         * Update Social Warfare Open Graph image if it's not set or if it was set by us.
         */
        $swp_og_image                      = get_post_meta( $post_id, 'swp_og_image', true );
        $swp_og_image_set_by_businesspress = get_post_meta( $post_id, 'swp_og_image_set_by_businesspress', true );

        if (
          // Is there no Social Warfare Pro image saved and no image being saved?
          ! $swp_og_image && empty( $_POST['swp_og_image'] ) ||
          // The image is there, but it's the same as Featured image and it was set by us
          $swp_og_image && intval( $_POST['swp_og_image'][0] ) === intval( $swp_og_image_set_by_businesspress )
        ) {
          update_post_meta( $post_id, 'swp_og_image', $thumb_id );
          update_post_meta( $post_id, 'swp_og_image_set_by_businesspress', $thumb_id );

          $did_set_social_warfare_image = true;

          /**
           * Also put the image into $_POST in case Social Warfare plugin gets to it later.
           * Otherwise it would remove the image which we just set.
           */
          $_POST['swp_og_image'] = array( $thumb_id );

        } else {
          delete_post_meta( $post_id, 'swp_og_image_set_by_businesspress', true );
        }
        break;
      }
    }
  }

  // Display notice once the page reloads after saving the post
  if ( $did_set_featured_image ) {
    add_filter(
      'redirect_post_location',
      function( $location ) {

        // Same condition as in core WordPress redirect_post()
        if (isset($_POST['save']) || isset($_POST['publish'])) {
          $location = add_query_arg('bp_featured_image', '1', $location);
        }

        return $location;
      }
    );
  }

  // Display notice once the page reloads after saving the post
  if ( $did_set_social_warfare_image ) {
    add_filter(
      'redirect_post_location',
      function( $location ) {

        // Same condition as in core WordPress redirect_post()
        if (isset($_POST['save']) || isset($_POST['publish'])) {
          $location = add_query_arg('bp_social_warfare_image', '1', $location);
        }

        return $location;
      }
    );
  }

}// end apt_publish_post()

/**
 * Function to fetch the image from URL and generate the required thumbnails
 */
function apt_generate_post_thumb($matches, $key, $post_content, $post_id)
{
  // Make sure to assign correct title to the image. Extract it from img tag
  $imageTitle = '';
  preg_match_all('/<\s*img [^\>]*title\s*=\s*[\""\']?([^\""\'>]*)/i', $post_content, $matchesTitle);
  
  if (count($matchesTitle) && isset($matchesTitle[1]) && !empty($matchesTitle[1][$key]) ) {
    $imageTitle = $matchesTitle[1][$key];
  }
  
  // Get the URL now for further processing
  $imageUrl = $matches[1][$key];
  
  // Get the file name
  $filename = substr($imageUrl, (strrpos($imageUrl, '/'))+1);
  
  if (!(($uploads = wp_upload_dir(current_time('mysql')) ) && false === $uploads['error'])) {
    return null;
  }
  
  // Generate unique file name
  $filename = wp_unique_filename( $uploads['path'], $filename );
  
  // Move the file to the uploads dir
  $new_file = $uploads['path'] . "/$filename";
  
  if (!ini_get('allow_url_fopen')) {
    $file_data = curl_get_file_contents($imageUrl);
  } else {
    $file_data = @file_get_contents($imageUrl);
  }
  
  if (!$file_data) {
    return null;
  }
  
  //Fix for checking file extensions
  $exts = explode(".",$filename);
  if(count($exts)>2) {
    return null;
  }
  $allowed_extensions = array_keys( get_allowed_mime_types() );
  $allowed = array();
  foreach ( $allowed_extensions as $extension ) {
    $allowed = array_merge( $allowed, explode( '|', $extension ) );
  }
  $ext=pathinfo($new_file,PATHINFO_EXTENSION);
  
  if( array_search($ext,$allowed) === false ) {
    return null;
  }
  
  file_put_contents($new_file, $file_data);
  
  // Set correct file permissions
  $stat = stat( dirname( $new_file ));
  $perms = $stat['mode'] & 0000666;
  @ chmod( $new_file, $perms );
  
  // Get the file type. Must to use it as a post thumbnail.
  $wp_filetype = wp_check_filetype( $filename );
  
  extract( $wp_filetype );
  
  // No file type! No point to proceed further
  if ( ( !$type || !$ext ) && !current_user_can( 'unfiltered_upload' ) ) {
    return null;
  }
  
  // Compute the URL
  $url = $uploads['url'] . "/$filename";
  
  // Construct the attachment array
  $attachment = array(
    'post_mime_type' => $type,
    'guid' => $url,
    'post_parent' => null,
    'post_title' => $imageTitle,
    'post_content' => '',
    );
  
  $thumb_id = wp_insert_attachment($attachment, $filename, $post_id);
  if ( !is_wp_error($thumb_id) ) {
    require_once(ABSPATH . '/wp-admin/includes/image.php');
    
    // Added fix by misthero as suggested
    wp_update_attachment_metadata( $thumb_id, wp_generate_attachment_metadata( $thumb_id, $new_file ) );
    update_attached_file( $thumb_id, $new_file );
    
    return $thumb_id;
  }
  
  return null;
}

/**
 * Function to fetch the contents of URL using curl in absense of allow_url_fopen.
 *
 * Copied from user comment on php.net (http://in.php.net/manual/en/function.file-get-contents.php#82255)
 */
function curl_get_file_contents($URL) {
  $c = curl_init();
  curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($c, CURLOPT_URL, $URL);
  $contents = curl_exec($c);
  curl_close($c);
  
  if ($contents) {
    return $contents;
  }
  
  return FALSE;
}

/**
 * Update Social Warfare Open Graph image if it has been set by us
 * and it's not the same as Featured image.
 * 
 * Or if it's not set at all.
 * 
 * Runs anytime the post is saved.
 */
add_action(
  'swpmb_after_save_post',
  function( $post_id ) {
    $thumbnail_id = !empty( $_REQUEST['_thumbnail_id'] ) ? intval( $_REQUEST['_thumbnail_id'] ) : false;

    // Featured image is not set
    if ( ! $thumbnail_id || -1 === intval( $thumbnail_id ) ) {
      return;
    }

    $swp_og_image                      = get_post_meta( $post_id, 'swp_og_image', true );
    $swp_og_image_set_by_businesspress = get_post_meta( $post_id, 'swp_og_image_set_by_businesspress', true );

    if (
      ! $swp_og_image ||
      $swp_og_image == $swp_og_image_set_by_businesspress &&
      $thumbnail_id != $swp_og_image
      ) {
      update_post_meta( $post_id, 'swp_og_image', $thumbnail_id );
      update_post_meta( $post_id, 'swp_og_image_set_by_businesspress', $thumbnail_id );

      // Display notice once the page reloads after saving the post
      add_filter(
        'redirect_post_location',
        function( $location ) {

          // Same condition as in core WordPress redirect_post()
          if (isset($_POST['save']) || isset($_POST['publish'])) {
            $location = add_query_arg('bp_social_warfare_image', '1', $location);
          }

          return $location;
        }
      );
    }
  }
);

/**
 * Display notices once the post is saved.
 */
add_action(
  'admin_notices',
  function() {
    if ( ! empty( $_GET['bp_featured_image'] ) ) {
      echo '<div class="updated"><p>BusinessPress: Featured image set.</p></div>';
    }

    if ( ! empty( $_GET['bp_social_warfare_image'] ) ) {
      echo '<div class="updated"><p>BusinessPress: Social Warfare Open Graph image set.</p></div>';
    }
  }
);

/**
 * Let WordPress core JavaScript remove the URL arguments used for these notices.
 * This way the URL looks clean after you save the post.
 */
add_filter(
  'removable_query_args',
  function( $removable_query_args ) {
    $removable_query_args[] = 'bp_featured_image';
    $removable_query_args[] = 'bp_social_warfare_image';

    return $removable_query_args;
  }
);
