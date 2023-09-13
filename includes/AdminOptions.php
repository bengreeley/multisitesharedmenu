<?php
/** 
 * Defines plugin options page and fields.
 */

namespace MultisiteSharedMenuPlugin;

class AdminOptions {
	
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_pluginfields') );
	}
	
	// Definintion of menu option for 'MFS'...
	public function add_admin_menu () {

		add_theme_page(
			'Multisite Shared Menu Settings',
			'Shared Menu Settings',
			'manage_options',
			'manage-mfs-options.php',
			[ $this, 'add_options_page' ]
		);
	}
	
	// Output WP admin options page...
	public function add_options_page () {
		require_once MULTISITE_SHARED_MENU_PLUGIN_PATH . 'views/options-page.php';
	}
	
	public function register_pluginfields() {
		register_setting( 'menufromsite-group', 'mfs_override_site_id', 'intval' );
		register_setting( 'menufromsite-group', 'mfs_override_menu_location', array( $this, 'validate_pluginvalues') );
	}

	// Error checking/validation.
	public function validate_pluginvalues( $input ) {	
		return $input;
	}
}
