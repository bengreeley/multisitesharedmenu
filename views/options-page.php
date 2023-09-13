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
							
							if ( intval( get_option('mfs_override_site_id') ) === $blog->blog_id ) {
								echo ' selected ';
							}
							
							echo '>';
							echo esc_html( $blog->domain . $blog->path );
							echo '</option>';
							
						} ?>
				</select>
				<p>
					<code><?php esc_html_e( 'Note: all sites must use the same theme to be compatible with each other', 'multisite-shared-menu' );?></code>
				</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="mfs_override_menu_location"><?php echo esc_html_e( 'Menu Location','multisite-shared-menu' );?>:</span>
				</th>
				<td>
			<?php
				$nav_menu_locations = get_registered_nav_menus();
				$locationKeys       = array_keys( $nav_menu_locations );
				$menu_location      = get_option('mfs_override_menu_location');
				
			if ( ! is_array( $menu_location ) ) {
				$menu_location = array( $menu_location );     // backwards-compatibility from previous version
			}

			if ( ! empty( $nav_menu_locations ) ) {
				$option_count = 1;

				echo '<ul id="navigation-menu-options">';
				foreach ( $locationKeys as $curLocation ) {

					if ( in_array ( $curLocation, $menu_location ) ) {
						$checked = true;
					}
					else {
						$checked = false;
					} ?>
					<li>
						<label for="mfs_override_menu_location[<?php echo esc_attr( $option_count ); ?>]">
							<input type="checkbox" id="mfs_override_menu_location[<?php echo esc_attr( $option_count ); ?>]" />
							<?php echo esc_html( $curLocation ); ?>
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
					<?php echo esc_html_e( 'Error: No navigation menus have been registered for this theme. Please view <a href="https://developer.wordpress.org/reference/functions/register_nav_menu/">WordPress documentation</a> to learn how to register navigation menus.', 'multisite-shared-menu' ); ?>
				</div>
				<?php
			} ?>
				</td>
			</tr>
			<tr>
				<td><?php submit_button(); ?></td>
			</tr>
			</tbody>
		</table>
	</form>
</div>
