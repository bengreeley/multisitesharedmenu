<?php

namespace MultisiteSharedMenuPlugin;

class MultisiteSharedMenu {
	
	public function __construct() {
		$this->register_admin_hooks();
		$this->register_public_hooks();
	}

	/**
	 * Register hooks needed for the admin
	 *
	 * @return void
	 */
	private function register_admin_hooks() {
		register_activation_hook( __FILE__,  [ $this, 'activate_plugin' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivate_plugin' ] );

		$admin_options = new AdminOptions();
	}

	/**
	 * Register hooks needed for the frontend menu
	 *
	 * @return void
	 */
	private function register_public_hooks() {
		add_filter( 'pre_wp_nav_menu' , [ $this, 'maybe_switch_to_site_menu' ], 10, 2 );
		add_filter( 'wp_nav_menu_items', [ $this, 'check_restore_blog_menu' ], 10, 2 );
	}

	/**
	 * Switches a navigation menu to a different site's menu if the menu is set in the shared menu settings and we are
	 * loading the menu location.
	 *
	 * @param $a
	 * @param $menu_object
	 * @return void
	 */
	public function maybe_switch_to_site_menu( $a, $menu_object ) {
		
		$multisite_shared_menu_settings = $this->get_saved_menu_settings();

		if ( ! isset( $menu_object ) ) {
			return;
		}

		if ( empty( $multisite_shared_menu_settings ) ) {
			return false;
		}
		
		$navigation_locations = $multisite_shared_menu_settings['destinationMenuLocation'];
		
		if( ! is_array( $navigation_locations ) ) {
			$navigation_locations = array( $navigation_locations ); // backwards-compatibility
		} 
		
		foreach ( $navigation_locations as $menu_location ) {
			
			if ( $menu_location === $menu_object->theme_location ) {
				$navigation_source_site = get_blog_details( $multisite_shared_menu_settings['sourceSiteID'] );
				switch_to_blog ( $navigation_source_site->blog_id );
				break;
			}
			
		}
		return;
	}
	
	// Switch back to the current blog/site if plugin settings were used.
	public function check_restore_blog_menu($items, $args) {
		if ( empty( $this->get_saved_menu_settings() ) ) {
			return;
		}

		restore_current_blog();
		return $items;
	}
	
	/**
	 * Retrives the Multisite Shared Menu settings in the parsed format
	 *
	 * @return boolean - empty array if settings are not set or are invalid, array of values otherwise
	 */
	private function get_saved_menu_settings() {
		
		$source_site_id            = get_option('mfs_override_site_id');
		$destination_menu_location = get_option( 'mfs_override_menu_location' );

		if (
			empty( $source_site_id ) ||
			empty( $destination_menu_location ) ||
			! is_numeric ( $source_site_id ) 
		) {
			return [];
		}

		return [ 
			'sourceSiteID'            => $source_site_id, 
			'destinationMenuLocation' => $destination_menu_location,
		];
	}

	/**
	 * Functionality to be run on plugin activation
	 *
	 * @return void
	 */
	public function activate_plugin() {
		
		// If the site isn't multisite, output a warning to the user that the plugin won't work
		if ( ! function_exists( 'is_multisite' ) || ! is_multisite() ) {
			add_action( 'admin_notices', function() { ?>
				<div id="warning" class="updated fade">
					<p><strong><?php esc_html_e( 'Multisite Shared Menu requires WordPress multisite to be configured.', 'multisite-shared-menu' );?></strong></p>
				</div><?php
			} );
			
			return; 
		}
	}

	/**
	 * Functions for deactivating the plugin
	 *
	 * @return void
	 */
	public function deactivate_plugin() {
		unregister_setting( 'menufromsite-group', 'mfs_override_menu_location' );
	}

}