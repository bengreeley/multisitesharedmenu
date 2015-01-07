<?php
/** 
 * Defines plugin options page and fields.
 */

class mfs_options_page {
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'mfs_options_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_pluginfields') );
	}
	
	// Definintion of menu option for 'MFS'...
	public function mfs_options_admin_menu () {

		add_theme_page(	'Multisite Shared Menu Settings',
							'Shared Menu Settings',
							'manage_options',
							'manage-mfs-options.php',
							array($this, 'mfs_options_page'));
	}
	
	// Output WP admin options page...
	public function mfs_options_page () {
		require_once plugin_dir_path( __FILE__ ) . '../views/options-page.php';
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

$options_page = new mfs_options_page;