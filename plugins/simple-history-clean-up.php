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
