<?php

add_filter( 'comment_form_default_fields', 'fv_cookies_comment_form_default_fields' );

function fv_cookies_comment_form_default_fields( $fields ) {

  if( empty($fields['cookies']) ) {
    $fields['cookies'] = sprintf(
      '<p class="comment-form-cookies-consent">%s %s</p>',
      '<input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="hidden" value="yes" />',
      __( 'If your comment is published it will be with the name and website above. Do not submit comment if you disagree.', 'businesspress' )
    );
  }

  return $fields;
}