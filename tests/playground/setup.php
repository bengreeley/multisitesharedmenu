<?php
/**
 * Builds the QA multisite for WordPress Playground. Run once by tests/playground/blueprint.json.
 *
 * Network: main site (1), Site A (2, /sitea/), Site B (3, /siteb/), all on Twenty Twenty-One.
 * Menus: main "MAIN-SITE"; A "SHARED-FROM-A" on primary; B "LOCAL-B" on primary and footer,
 * plus a Navigation Menu widget. The plugin is activated on Site B only, unconfigured.
 * PHP errors are logged to tests/playground/logs/debug.log on the host.
 *
 * Test tooling only: never loaded by the plugin.
 *
 * @package MultisiteSharedMenu
 */

/**
 * Creates a site in the current network under a subdirectory, or returns the existing one.
 *
 * @param string $slug  Subdirectory slug.
 * @param string $title Site title.
 * @return int Blog ID.
 * @throws RuntimeException If the site can't be created.
 */
function menufromsite_qa_create_site( $slug, $title ) {
	$network = get_network();
	$path    = $network->path . $slug . '/';
	$site_id = get_blog_id_from_url( $network->domain, $path );

	if ( ! $site_id ) {
		$site_id = wpmu_create_blog( $network->domain, $path, $title, 1 );
	}

	if ( is_wp_error( $site_id ) ) {
		throw new RuntimeException( 'Could not create site ' . $slug . ': ' . $site_id->get_error_message() );
	}

	return (int) $site_id;
}

/**
 * Activates Twenty Twenty-One and creates a menu with one custom link on the current site.
 *
 * @param string   $menu_name  Menu name.
 * @param string   $item_title Menu item title, used as the marker in QA checks.
 * @param string[] $locations  Theme locations to assign the menu to.
 * @return int Menu term ID.
 */
function menufromsite_qa_setup_menu( $menu_name, $item_title, $locations ) {
	switch_theme( 'twentytwentyone' );

	$menu = wp_get_nav_menu_object( $menu_name );
	if ( $menu ) {
		return (int) $menu->term_id;
	}

	$menu_id = wp_create_nav_menu( $menu_name );
	wp_update_nav_menu_item(
		$menu_id,
		0,
		array(
			'menu-item-title'  => $item_title,
			'menu-item-url'    => 'https://example.test/' . strtolower( $item_title ),
			'menu-item-type'   => 'custom',
			'menu-item-status' => 'publish',
		)
	);

	$assigned = array();
	foreach ( $locations as $location ) {
		$assigned[ $location ] = $menu_id;
	}
	set_theme_mod( 'nav_menu_locations', $assigned );

	return (int) $menu_id;
}

/**
 * Adds a Navigation Menu widget for the given menu to the theme's first sidebar on the current site.
 *
 * @param int $menu_id Menu term ID.
 */
function menufromsite_qa_add_menu_widget( $menu_id ) {
	update_option(
		'widget_nav_menu',
		array(
			2              => array(
				'title'    => 'B Widget',
				'nav_menu' => $menu_id,
			),
			'_multiwidget' => 1,
		)
	);

	$sidebars              = get_option( 'sidebars_widgets', array() );
	$sidebars['sidebar-1'] = array( 'nav_menu-2' );
	update_option( 'sidebars_widgets', $sidebars );
}

/**
 * Points Playground's wp-content/debug.log at the mounted tests/playground/logs/debug.log.
 *
 * Playground defines WP_DEBUG_LOG as true before wp-config.php runs, so WordPress always logs to
 * wp-content/debug.log in Playground's in-memory filesystem. A symlink makes that file readable on the host.
 */
function menufromsite_qa_link_debug_log() {
	$log    = WP_CONTENT_DIR . '/debug.log';
	$target = WP_CONTENT_DIR . '/qa-logs/debug.log';

	if ( is_link( $log ) || ! is_dir( dirname( $target ) ) ) {
		return;
	}
	if ( file_exists( $log ) ) {
		wp_delete_file( $log );
	}
	symlink( $target, $log );
}

// Blueprint PHP runs outside a web request; wpmu_create_blog() logs the client IP and warns without one.
if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
	$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}

menufromsite_qa_link_debug_log();

WP_Theme::network_enable_theme( 'twentytwentyone' );

menufromsite_qa_setup_menu( 'Main Menu', 'MAIN-SITE', array( 'primary' ) );

$menufromsite_qa_site_a = menufromsite_qa_create_site( 'sitea', 'Site A' );
$menufromsite_qa_site_b = menufromsite_qa_create_site( 'siteb', 'Site B' );

switch_to_blog( $menufromsite_qa_site_a );
menufromsite_qa_setup_menu( 'A Menu', 'SHARED-FROM-A', array( 'primary' ) );
restore_current_blog();

switch_to_blog( $menufromsite_qa_site_b );
menufromsite_qa_add_menu_widget( menufromsite_qa_setup_menu( 'B Menu', 'LOCAL-B', array( 'primary', 'footer' ) ) );
$menufromsite_qa_activated = activate_plugin( 'multisitesharedmenu/multisitesharedmenu.php' );
if ( is_wp_error( $menufromsite_qa_activated ) ) {
	throw new RuntimeException( 'Could not activate plugin on Site B: ' . $menufromsite_qa_activated->get_error_message() );
}
restore_current_blog();
