<?php

/**
 * Activator class includes functions/hooks for plugin activation process.
 */
class MultisiteSharedMenu_Activator {

	public static function activate() {
		if ( ! function_exists( 'is_multisite' ) ) {

			add_action( 'admin_notices', [ $this, 'mfs_version_warning' ] );
			
			return; 
		}
	}

	public function mfs_version_warning() {
		echo '<div id="warning" class="updated fade"><p><strong>Multisite Shared Menu requires WordPress multisite to be configured.</strong></p></div>';
	}
	
}
