<?php
/**
 * Core plugin class: renders a menu from another site on the network.
 *
 * @package MultisiteSharedMenu
 */

/**
 * Switches to the configured source site while selected menu locations render.
 */
class MasterSharedMenu {

	/**
	 * Unused; kept for backward compatibility.
	 *
	 * @var mixed
	 */
	protected $loader;

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * True while a shared menu is rendering from the source site.
	 *
	 * @var bool
	 */
	protected $switched = false;

	/**
	 * Sets up the plugin and registers its hooks.
	 */
	public function __construct() {
		$this->plugin_name = 'multisitesharedmenu';
		$this->version     = '1.2';

		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Registers admin-side functionality.
	 */
	private function define_admin_hooks() {
		$this->add_mfs_menu();
	}

	/**
	 * Registers the nav menu filters that switch to and from the source site.
	 */
	private function define_public_hooks() {
		add_filter( 'pre_wp_nav_menu', array( $this, 'menuswitch_check' ), 10, 2 );
		add_filter( 'wp_nav_menu_items', array( $this, 'check_restore_blog_menu' ), 10, 1 );
	}

	/**
	 * Switches to the source site when the menu being rendered is in a selected location.
	 *
	 * Hooked to `pre_wp_nav_menu`.
	 *
	 * @param string|null $output      Short-circuit output from earlier filters, or null.
	 * @param object      $menu_object The wp_nav_menu() arguments.
	 * @return string|null The incoming output when unconfigured, otherwise null so WordPress renders the menu.
	 */
	public function menuswitch_check( $output, $menu_object ) {
		// Not configured: pass through so WordPress renders the site's own menu.
		$mfs_settings = $this->validate_mfs_set();
		if ( ! $mfs_settings ) {
			return $output;
		}

		$navigation_affected = $mfs_settings['destinationMenuLocation'];

		if ( ! is_array( $navigation_affected ) ) {
			$navigation_affected = array( $navigation_affected ); // Backwards-compatibility.
		}

		// Nav menus are stored as an array; loop through each item.
		foreach ( $navigation_affected as $menu ) {
			if ( isset( $menu_object ) ) {
				if ( $menu_object->theme_location === $menu ) {
					$switch_site = get_blog_details( $mfs_settings['sourceSiteID'] );
					if ( $switch_site ) {
						switch_to_blog( $switch_site->blog_id );
						$this->switched = true;
						break;
					}
				}
			}
		}

		return null;
	}

	/**
	 * Switches back to the current site if this plugin switched for this menu.
	 *
	 * Uses a flag rather than the settings, which would be read from the source site here.
	 * Hooked to `wp_nav_menu_items`.
	 *
	 * @param string $items The menu items HTML.
	 * @return string The menu items HTML, unchanged.
	 */
	public function check_restore_blog_menu( $items ) {
		if ( ! $this->switched ) {
			return $items;
		}

		$this->switched = false;
		restore_current_blog();
		return $items;
	}

	/**
	 * Reads and checks the plugin settings.
	 *
	 * @return array|false False if the settings are not set or invalid, otherwise the source site ID and menu location(s).
	 */
	private function validate_mfs_set() {

		$source_site_id            = get_option( 'mfs_override_site_id' );
		$destination_menu_location = get_option( 'mfs_override_menu_location' );

		if ( ( ! strlen( $source_site_id ) ||
				empty( $destination_menu_location ) ||
				! is_numeric( $source_site_id ) ||
				(int) $source_site_id < 1 ) ) {

			return false;
		}

		return array(
			'sourceSiteID'            => $source_site_id,
			'destinationMenuLocation' => $destination_menu_location,
		);
	}

	/**
	 * Returns the plugin slug.
	 *
	 * @return string
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Returns the plugin version.
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Includes the file that defines the menu settings page.
	 */
	public function add_mfs_menu() {
		require_once plugin_dir_path( __FILE__ ) . '../admin/class-menufromsite-admin-options.php';
	}
}
