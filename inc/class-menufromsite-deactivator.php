<?php
/**
 * Plugin deactivation.
 *
 * @package MultisiteSharedMenu
 */

/**
 * Functions and hooks for plugin deactivation, cleaning up settings.
 */
class MasterSharedMenu_Deactivator {

	/**
	 * Runs on plugin deactivation.
	 */
	public static function deactivate() {
		self::unregister_pluginfields();
	}

	/**
	 * Unregisters the plugin's menu location setting.
	 */
	private static function unregister_pluginfields() {

		unregister_setting( 'menufromsite-group', 'mfs_override_menu_location' );
	}
}
