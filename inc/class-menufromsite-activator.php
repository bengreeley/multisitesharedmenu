<?php

// Functions/hooks for plugin activation process.
class MasterSharedMenu_Activator {

	public static function activate() {
		if ( !function_exists('is_multisite') ) {
        
	        function mfs_version_warning() {
	            echo '<div id="warning" class="updated fade"><p><strong>Menu from Site requires WordPress multisite to be configured.</strong></p></div>';
	        }
	        add_action('admin_notices', 'mfs_version_warning'); 
	        
	        return; 
		}
	}
}