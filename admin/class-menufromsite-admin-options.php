<?php
/**
 * Defines plugin options page and fields.
 *
 * @package MultisiteSharedMenu
 */

/**
 * Registers the Appearance → Shared Menu Settings page and its settings.
 */
class MasterSharedMenu_Options_Page {

	/**
	 * Hooks the settings page and settings registration.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'mfs_options_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_pluginfields' ) );
	}

	/**
	 * Adds the settings page under Appearance.
	 */
	public function mfs_options_admin_menu() {

		add_theme_page(
			'Multisite Shared Menu Settings',
			'Shared Menu Settings',
			'manage_options',
			'manage-mfs-options.php',
			array( $this, 'mfs_options_page' )
		);
	}

	/**
	 * Outputs the settings page.
	 */
	public function mfs_options_page() {
		require_once plugin_dir_path( __FILE__ ) . '../views/options-page.php';
	}

	/**
	 * Registers the plugin settings.
	 */
	public function register_pluginfields() {

		register_setting( 'menufromsite-group', 'mfs_override_site_id', 'intval' );
		register_setting( 'menufromsite-group', 'mfs_override_menu_location', array( $this, 'validate_pluginvalues' ) );
	}

	/**
	 * Sanitize callback for the menu location setting. Currently returns the input unchanged.
	 *
	 * @param mixed $input The submitted value.
	 * @return mixed The value to save.
	 */
	public function validate_pluginvalues( $input ) {

		return $input;
	}
}

$menufromsite_options_page = new MasterSharedMenu_Options_Page();
