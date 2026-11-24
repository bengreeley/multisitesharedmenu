<?php
/**
 * QA probe for the shared-menu blog switching.
 *
 * - Appends <!--mfsqa blog=N switched=0|1--> after every rendered wp_nav_menu(), so the blog
 *   context left behind by each menu is visible in the page source.
 * - ?mfsqa_stack=1 simulates another plugin switching blogs around menu renders, and reports
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
 * Simulates another plugin's switch_to_blog() around menu renders, and reports whether it survived.
 *
 * The outer switch targets the current blog, so the plugin's own settings still apply. Each theme
 * location is then rendered by theme_location, which is what the plugin matches on: a shared
 * location makes the plugin switch and restore, and an unshared one must leave the stack alone.
 * After every menu the outer switch should still be in place (switched=1, same blog).
 */
function menufromsite_qa_probe_stack() {
	if ( ! menufromsite_qa_probe_requested() ) {
		return;
	}

	header( 'Content-Type: text/plain' );
	$home = get_current_blog_id();

	switch_to_blog( $home );
	printf( "home=%d outer_switch_blog=%d switched=%d\n", (int) $home, (int) get_current_blog_id(), ms_is_switched() ? 1 : 0 );

	foreach ( array( 'primary', 'footer' ) as $location ) {
		$menu = wp_nav_menu(
			array(
				'theme_location' => $location,
				'echo'           => false,
				'fallback_cb'    => false,
			)
		);
		$text = $menu ? trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $menu ) ) ) : '';
		printf( "location=%s after_menu_blog=%d switched=%d menu=%s\n", $location, (int) get_current_blog_id(), ms_is_switched() ? 1 : 0, $text );
	}

	restore_current_blog();
	printf( "after_own_restore_blog=%d switched=%d\n", (int) get_current_blog_id(), ms_is_switched() ? 1 : 0 );
	exit;
}
add_action( 'template_redirect', 'menufromsite_qa_probe_stack' );
