<?php

/**
 * Remove Simple History wp-admin add-on ads etc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Remove the "Unlock more features with Simple History Premium!" box from Simple History main screen.
add_filter(
	'simple_history/core_dropins',
	function($dropins) {
		// Filter out the dropin from the array
		return array_filter( $dropins, function( $dropin ) {
			return $dropin !== 'Simple_History\\Dropins\\Sidebar_Add_Ons_Dropin';
		});
	}
);

// Remove "Review this plugin if you like it", "Support our work" and "Need help?" from the Simple History sidebar.
add_filter(
	'simple_history/SidebarDropin/default_sidebar_boxes',
	function( $boxes ) {
		unset( $boxes['boxReview'] );
		unset( $boxes['boxDonate'] );
		unset( $boxes['boxSupport'] );
		return $boxes;
	}
);

// Remove the "Simple History" header from the Simple History main screen to save vertical space.
add_action(
	'admin_head',
	function() {
		if ( ! empty( $_GET['page'] ) && 'simple_history_admin_menu_page' === $_GET['page'] ) {
			?>
			<style>
				.sh-PageHeader {
					display: none;
				}
			</style>
			<?php
		}
	}
);


/**
 * Force Simple History settings to BusinessPress defaults.
 */

// Settings -> "History menu position" should be "Inside dashboard menu item" as by default it's above "Posts"
add_filter(
	'simple_history/admin_menu_location',
	function( $location ) {
		return 'inside_dashboard';
	}
);

// Settings -> "Show history" -> "in the admin bar" should be disabled for performance reasons
add_filter( 'simple_history_show_in_admin_bar', '__return_false' );

add_action(
	'simple_history/settings_page/general_section_output',
	function() {
		?>
		<style>
			.businesspress-simple-history-setting-note {
				background: var(--sh-color-cream);
				padding: 2px;
				border-radius: 5px;
				margin-top: 10px;
			}
		</style>
		<script>
			jQuery( function($) {
				$( '#simple_history_show_in_admin_bar, [name=simple_history_menu_page_location]' ).prop( 'disabled', true );
				$( 'label[for=simple_history_show_in_admin_bar]' ).append( '<span class="businesspress-simple-history-setting-note">BusinessPress is disabling this for performance reasons.</span>' );
				$( '[name=simple_history_menu_page_location][value=inside_dashboard]' ).parent().append( '<span class="businesspress-simple-history-setting-note">BusinessPress is forcing this setting as the default setting moves it above "Posts" in the admin menu.</span>' );
			});
		</script>
		<?php
	}
);
