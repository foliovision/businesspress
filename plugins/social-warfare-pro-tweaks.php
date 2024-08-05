<?php

/**
 * Social Warfare Custom Options meta box on post editing is too big with Social Warfare Pro.
 * 
 * We do not use Pinterest image much, so let's hide it behind a toggle.
 */

add_action( 'admin_footer-post.php', 'businesspress_social_warfare_pro_pinterest_toggle' );
add_action( 'admin_footer-post-new.php', 'businesspress_social_warfare_pro_pinterest_toggle' );

function businesspress_social_warfare_pro_pinterest_toggle() {
  global $post;

	// Do not hide if there's anything set.
  if (
		get_post_meta( $post->ID, 'swp_pinterest_image', true ) ||
		get_post_meta( $post->ID, 'swp_pinterest_description', true )
	) {
    return;
  }

  ?>
<script>
jQuery( function($) {
	var businesspress_social_warfare_pro_pinterest_toggle = $('<div class="swpmb-meta-container" data-type="twitter_og_toggle"><div class="swpmb-full-width-wrap swpmb-flex"></div><div class="swpmb-left-wrap swpmb-flex"><div class="swpmb-field swpmb-switch-wrapper twitter_og_toggle swpmb-left"><div class="swpmb-label" id="businesspress_social_warfare_pro_pinterest_toggle-label"><label for="businesspress_social_warfare_pro_pinterest_toggle">Add Pinterest Image</label></div><div class="swpmb-input"><label class="swpmb-switch-label swpmb-switch-label--square">\
		<input value="1" type="checkbox" id="businesspress_social_warfare_pro_pinterest_toggle" class="swpmb-switch" name="businesspress_social_warfare_pro_pinterest_toggle" aria-labelledby="businesspress_social_warfare_pro_pinterest_toggle-label" >\
		<div class="swpmb-switch-status">\
			<span class="swpmb-switch-slider"></span>\
			<span class="swpmb-switch-on">Yes</span>\
			<span class="swpmb-switch-off">No</span>\
		</div>\
		</label>\
	</div></div></div><div class="swpmb-right-wrap swpmb-flex"></div></div>');

	businesspress_social_warfare_pro_pinterest_toggle.on( 'click', function() {
		$('.swpmb-meta-container[data-type=pinterest]').toggle(
			$( '#businesspress_social_warfare_pro_pinterest_toggle' ).prop( 'checked' )
		);
	});

  setTimeout( function() {
	  $('.swpmb-meta-container[data-type=pinterest]').hide().before( businesspress_social_warfare_pro_pinterest_toggle );
  }, 10 );
});
</script>
  <?php
}
