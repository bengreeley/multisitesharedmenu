<?php

class MasterSharedMenu {
	protected $loader;
	protected $plugin_name;
	protected $version;

	public function __construct() {
		$this->plugin_name = 'multisitesharedmenu';
		$this->version = '1.2';

		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	private function define_admin_hooks() {
		$this->add_mfs_menu();
	}

	private function define_public_hooks() {
		add_filter( 'pre_wp_nav_menu' , array( $this, 'menuswitch_check'), 10, 2 );
		add_filter( 'wp_nav_menu_items', array( $this, 'check_restore_blog_menu' ), 10, 2 );
	}

	// Checks if options set to switch menu. If so, switch to the appropriate site and change the menu to load the desired menu....
	public function menuswitch_check( $a, $menu_object ) {
		if( !( $mfsSettings = $this->validate_mfs_set() )) {
			return false;
		}
		
		$navigation_affected = $mfsSettings['destinationMenuLocation'];
		
		if( !is_array( $navigation_affected ) ) {
			$navigation_affected = array( $navigation_affected ); // backwards-compatibility
		} 
		
		// nav menus now stored as an array, loop through each item
		foreach ( $navigation_affected as $menu) {
			if (isset( $menu_object )) {
				if( $menu == $menu_object->theme_location ) {
					$switchSite = get_blog_details($mfsSettings['sourceSiteID']);
					switch_to_blog($switchSite->blog_id);
				}
			}
		}
		return;
	}
	
	// Switch back to the current blog/site if plugin settings were used.
	public function check_restore_blog_menu($items, $args) {
		restore_current_blog();
		return $items;
	}
	
	//Returns false if the settings have not been set or there are invalid values saved. Returns array of values otherwise.
	private function validate_mfs_set() {
		
		$sourceSiteID = get_option('mfs_override_site_id');
		$destinationMenuLocation = get_option( 'mfs_override_menu_location' );

		if(( 	!strlen( $sourceSiteID ) ||
				empty( $destinationMenuLocation ) ||
				!is_numeric ($sourceSiteID ))) {
					
			return false;
		}

		return array( 'sourceSiteID' => $sourceSiteID, 'destinationMenuLocation' => $destinationMenuLocation );
	}

	public function get_plugin_name() {
		return $this->plugin_name;
	}

	public function get_version() {
		return $this->version;
	}
	
	// Includes file that will define menu settings page...
	public function add_mfs_menu() {
		require_once plugin_dir_path( __FILE__ ) . '../admin/class-menufromsite-admin-options.php';
	}

}