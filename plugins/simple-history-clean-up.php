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
		// Target Simple History dashboard page
		if ( ! empty( $_GET['page'] ) && 'simple_history_admin_menu_page' === $_GET['page'] ) {
			businesspress_remove_simple_history_texts();

			?>
			<style>
				.sh-PageHeader, .sh-NotificationBar, .sh-PremiumFeaturesPostbox-button {
					display: none;
				}
			</style>
			<?php

		// Target Simple History settings page
		} else if ( ! empty( $_GET['page'] ) && 'simple_history_settings_page' === $_GET['page'] ) {
			businesspress_remove_simple_history_texts();

			?>
			<style>
				.sh-PageHeader-rightLink a[href*=premium], /* Add-ons link in header */
				.sh-NotificationBar, /* Yellow bar on top of the page */
				.sh-PremiumFeaturesPostbox, /* Right sidebar ad boxes */
				.sh-StatsDashboard-dateRangeControls-description, /* Upgrade to Premium to get access to more date ranges. */
				.sh-StatsDashboard-card:has(.is-blurred), /* blurred graphs on "Stats & Summaries" page */
				.sh-PremiumFeatureBadge, /* Greed "New" badge in header links */
				a.sh-ExternalLink[href*=premium_upsell], /* "Upgrade to Simple History Premium to set this to any number of days." link */
				.sh-PageNav a[href*=promo_upsell] /* "Upgrade to Premium for more features" link in header */{
					display: none;
				}
			</style>
			<?php
		}
	}
);

function businesspress_remove_simple_history_texts() {
	add_filter(
		'gettext',
		function( $translation, $text, $domain ) {
			if ( 'simple-history' === $domain ) {
				if (
					stripos( $text, 'a nice review at WordPress.org' ) !== false
				) {
					return '';
				}
			}
			return $translation;
		},
		10,
		3
	);
}

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
