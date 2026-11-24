<?php
/**
 * QA probe for the shared-menu blog switching.
 *
 * - Appends <!--mfsqa blog=N switched=0|1--> after every rendered wp_nav_menu(), so the blog
 *   context left behind by each menu is visible in the page source.
 * - ?mfsqa_stack=1 simulates another plugin switching to blog 1 around a menu render, and reports
 *   whether that outer switch survived.
 *
 * Test tooling only: lives in tests/playground/ and is never loaded by the plugin. Do not ship.
 *
 * @package MultisiteSharedMenu
 */

/**
 * Appends the blog context marker after a rendered menu.
 *
 * @param string $html Menu HTML.
 * @return string Menu HTML with the marker appended.
 */
function menufromsite_qa_probe_marker( $html ) {
	return $html . sprintf( '<!--mfsqa blog=%d switched=%d-->', get_current_blog_id(), ms_is_switched() ? 1 : 0 );
}
add_filter( 'wp_nav_menu', 'menufromsite_qa_probe_marker', 999 );

/**
 * Whether the current request asked for the switch-stack check.
 *
 * Reads the raw query string instead of $_GET; the flag is a read-only, test-only switch.
 *
 * @return bool
 */
function menufromsite_qa_probe_requested() {
	$query = isset( $_SERVER['QUERY_STRING'] ) ? sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '';
	parse_str( $query, $args );
	return isset( $args['mfsqa_stack'] ) && '1' === $args['mfsqa_stack'];
}

/**
 * Switches to blog 1, renders a menu, and reports whether this outer switch survived.
 */
function menufromsite_qa_probe_stack() {
	if ( ! menufromsite_qa_probe_requested() ) {
		return;
	}

	header( 'Content-Type: text/plain' );
	$home = get_current_blog_id();

	switch_to_blog( 1 );
	$menus = wp_get_nav_menus();
	$menu  = wp_nav_menu(
		array(
			'menu'        => $menus ? $menus[0]->term_id : 0,
			'echo'        => false,
			'fallback_cb' => false,
		)
	);
	printf( "home=%d after_menu_blog=%d switched=%d menu_rendered=%d\n", (int) $home, (int) get_current_blog_id(), ms_is_switched() ? 1 : 0, $menu ? 1 : 0 );
	restore_current_blog();
	printf( "after_own_restore_blog=%d switched=%d\n", (int) get_current_blog_id(), ms_is_switched() ? 1 : 0 );
	exit;
}
add_action( 'template_redirect', 'menufromsite_qa_probe_stack' );
