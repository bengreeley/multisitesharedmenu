<?php

// Functions/hooks for plugin deactivation process for cleaning up of any settings.

class MasterSharedMenu_Deactivator {

	public static function deactivate() {
		MasterSharedMenu_Deactivator::unregister_pluginfields();
	}
	
	private function unregister_pluginfields() {
		 
		unregister_setting( 'menufromsite-group', 'mfs_override_menu_location' );
	}

}