<?php
/**
 * Plugin activation.
 *
 * @package MultisiteSharedMenu
 */

/**
 * Functions and hooks for the plugin activation process.
 */
class MasterSharedMenu_Activator {

	/**
	 * Runs on plugin activation. Shows an admin notice if multisite isn't available.
	 */
	public static function activate() {
		if ( ! function_exists( 'is_multisite' ) ) {

			/**
			 * Outputs the "multisite required" admin notice.
			 */
			function menufromsite_version_warning() {
				echo '<div id="warning" class="updated fade"><p><strong>Menu from Site requires WordPress multisite to be configured.</strong></p></div>';
			}
			add_action( 'admin_notices', 'menufromsite_version_warning' );

			return;
		}
	}
}
