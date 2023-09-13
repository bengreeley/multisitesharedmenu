<?php
/** 
 * Defines plugin options page and fields.
 */

namespace MultisiteSharedMenuPlugin;

class AdminOptions {
	
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_pluginfields') );
	}
	
	/**
	 * Definition of menu option for Multisite Shared Menu
	 *
	 * @return void
	 */
	public function add_admin_menu () {

		add_theme_page(
			'Multisite Shared Menu Settings',
			'Shared Menu Settings',
			'manage_options',
			'manage-mfs-options.php',
			[ $this, 'output_options_page' ]
		);
	}
	
	public function register_pluginfields() {
		register_setting( 'menufromsite-group', 'mfs_override_site_id', 'intval' );
		register_setting( 'menufromsite-group', 'mfs_override_menu_location', [
			'sanitize_callback' => [ $this, 'validate_pluginvalues' ],
			'show_in_rest'      => true,
			'type'              => 'array',
		] );
	}

	/**
	 * Validation for the menu locations array
	 *
	 * @param array $input
	 * @return array of sanitized input
	 */
	public function validate_pluginvalues( $input ) {
		return array_map( 'sanitize_text_field', (array)$input );
	}

	/**
	 * Outputs the content for the options page
	 *
	 * @return void
	 */
	public function output_options_page() { 
		
		$nav_menu_locations = get_registered_nav_menus();
		$override_site_id   = get_option( 'mfs_override_site_id' );
		$location_keys      = array_keys( $nav_menu_locations );
		$saved_locations    = get_option( 'mfs_override_menu_location' );
		
		?>
		<div class="wrap">	
			<h2><?php esc_html_e( 'Multisite Shared Menu Settings', 'multisite-shared-menu' ); ?></h2>
			<p><?php esc_html_e( 'Select the source site that will be used for the selected menu location(s).', 'multisite-shared-menu' ); ?></p>	
			<form method="post" action="options.php">	    
				<table class="form-table">
					<tbody>
				<?php
					settings_fields( 'menufromsite-group' );
					do_settings_sections( 'menufromsite-group' );
					
					// Output dropdown menu of available sites, excluding the current site
					$blogs = get_sites( [
						'site__not_in' => get_current_blog_id(),
					] );
			
				?>
					<tr>
						<th scope="row">
							<label for="mfs_override_site_id"><?php echo esc_html_e( 'Source Site', 'multisite-shared-menu' ); ?>:</label>
						</th>
						<td>
							<select name="mfs_override_site_id" id="mfs_override_site_id">
								<option value="">-- Select --</option>
								<?php

								foreach( $blogs as $blog ) {
									echo '<option value="' . esc_attr( $blog->blog_id ) . '"';
									
									if ( intval( $override_site_id ) === intval( $blog->blog_id ) ) {
										echo ' selected ';
									}
									
									echo '>';
									echo esc_html( $blog->domain . $blog->path );
									echo '</option>';
									
								} ?>
						</select>
						<p>
							<code><?php esc_html_e( 'Note: Sites must use the same theme in order to share menus.', 'multisite-shared-menu' );?></code>
						</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label><?php echo esc_html_e( 'Menu Location','multisite-shared-menu' );?>:</span>
						</th>
						<td>
					<?php
						
					if ( ! is_array( $saved_locations ) ) {
						$saved_locations = array( $saved_locations );     // backwards-compatibility from previous version
					}

					if ( ! empty( $nav_menu_locations ) ) {
						$option_count = 1;

						echo '<ul id="navigation-menu-options">';
						foreach ( $location_keys as $current_location ) {

							if ( true === in_array( $current_location, $saved_locations ) ) {
								$checked = true;
							}
							else {
								$checked = false;
							} ?>
							<li>
								<label for="mfs_override_menu_location[<?php echo esc_attr( $option_count ); ?>]">
									<input 
										<?php checked( $checked ); ?> 
										type="checkbox" 
										id="mfs_override_menu_location[<?php echo esc_attr( $option_count ); ?>]" 
										name="mfs_override_menu_location[]" 
										value="<?php echo esc_attr( $current_location );?>" />
									<?php echo esc_html( $current_location ); ?>
								</label>
							</li>
							<?php

							$option_count++;
						}
						echo '</ul>';
					}
					else {
						// No menu locations ?>
						<div class="error-message">
							<?php esc_html_e( 'Error: No navigation menus have been registered for this theme. Please view <a href="https://developer.wordpress.org/reference/functions/register_nav_menu/">WordPress documentation</a> to learn how to register navigation menus.', 'multisite-shared-menu' ); ?>
						</div>
						<?php
					} ?>
						<code><?php esc_html_e( 'Select the menu location(s) to use from the selected site. The menu items will be directly referenced from that site instead of the current site.', 'multisite-shared-menu' );?></code>
						</td>
					</tr>
					<tr>
						<td><?php submit_button(); ?></td>
					</tr>
					</tbody>
				</table>
			</form>
		</div>

		<?php
	}
}
