<?php
/**
 * Class to purge the Surge WordPress page cache plugin cache
 *
 * @package BusinessPress
 */

/**
 * Class to purge the Surge WordPress page cache plugin cache
 */
class BusinessPress_Surge_Cache_Purge {

	/**
	 * Hook in the actions
	 */
	public function __construct() {
		add_action(
			'admin_bar_menu',
			function( $wp_admin_bar ) {
				if ( is_admin() ) {
					$wp_admin_bar->add_node(
						array(
							'id'    => 'businesspress-surge-cache-purge',
							'title' => 'Surge Purge',
							'href'  => '#',
						)
					);
				}

			},
			999
		);

		add_action(
			'admin_footer',
			function() {
				?>
					<script>
					jQuery( function($) {
						let purging = false;

						$( '#wp-admin-bar-businesspress-surge-cache-purge' ).on(
							'click',
							function() {
								let button = $(this).find('a');

								if ( purging ) return false;

								purging = true;
								button.text( 'Purging...');

								$.post(
									ajaxurl,
									{
										action: 'businesspress_surge_cache_purge',
										nonce: '<?php echo esc_js( wp_create_nonce( 'businesspress-surge-cache-purge' ) ); ?>',
									},
									function( response ) {
										purging = false;

										if ( response.success ) {
											button.text( response.data );
											setTimeout( function() {
												button.text( 'Surge Purge' );
											}, 2000 );

										} else {
											button.text( 'Surge Purge' );

											if ( response.data ) {
												alert( response.data );
											} else {
												alert( 'BusinessPress Surge Cache Purge failed: '+response );

											}

										}
									}
								);

								return false;
							}
						);
					});
					</script>
				<?php
			}
		);

		add_action(
			'wp_ajax_businesspress_surge_cache_purge',
			function() {

				if ( ! wp_verify_nonce( $_POST['nonce'], 'businesspress-surge-cache-purge' ) ) {
					wp_send_json_error( 'BusinessPress: Nonce verification failed.' );
				}

				$surge_cache_path = WP_CONTENT_DIR . '/cache/surge';

				if ( file_exists( $surge_cache_path ) ) {
					$fs = new WP_Filesystem_Direct( false );
					$r  = $fs->rmdir( WP_CONTENT_DIR . '/cache/surge', true );

					if ( $r ) {
						wp_send_json_success( 'Success!' );

					} else {
						wp_send_json_error( 'BusinessPress: Surge cache folder failed to delete!' );
					}
				} else {
					wp_send_json_success( 'Success!' );
				}
			}
		);
	}
}

new BusinessPress_Surge_Cache_Purge();
