<?php
/**
 * Settings page markup for Appearance → Shared Menu Settings.
 *
 * Included from MasterSharedMenu_Options_Page::mfs_options_page().
 *
 * @package MultisiteSharedMenu
 */

?>
<div class="wrap">
			
	<h2>Multisite Shared Menu Settings</h2>
	<p>Select the source site that will be used for the selected menu location(s).</p>
	<p>Please Note: Use the same theme on each site to ensure menu location compatibility.</p>
		
	<form method="post" action="options.php">	    
		<table class="form-table">
			<tbody>
		<?php
		settings_fields( 'menufromsite-group' );
		do_settings_sections( 'menufromsite-group' );

		// Output dropdown menu of available sites.
		$menufromsite_sites = wp_get_sites();

		echo '<tr>
					<th scope="row"><label for="mfs_override_site_id">Source Site:</label></th>';

		echo '<td>
					<select name="mfs_override_site_id" id="mfs_override_site_id">';

		echo '<option value="">-- Select --</option>';
		foreach ( $menufromsite_sites as $menufromsite_site ) {
			// wp_get_sites() returns IDs as strings; cast so strict comparisons behave like the old loose ones.
			if ( get_current_blog_id() !== (int) $menufromsite_site['blog_id'] ) {

				echo '<option value="' . $menufromsite_site['blog_id'] . '"';

				if ( (int) get_option( 'mfs_override_site_id' ) === (int) $menufromsite_site['blog_id'] ) {
					echo ' selected ';
				}

				echo '>' . $menufromsite_site['domain'] . $menufromsite_site['path'] . '</option>';

			}
		}

		echo '</select>
				</td>
			</tr>';

		// Output available theme menu locations.
		echo '<tr>
			<th scope="row"><label for="mfs_override_menu_location">Menu Location:</span></th>
			<td>';

		$menufromsite_locations       = get_registered_nav_menus();
		$menufromsite_location_keys   = array_keys( $menufromsite_locations );
		$menufromsite_saved_locations = get_option( 'mfs_override_menu_location' );

		if ( ! is_array( $menufromsite_saved_locations ) ) {
			$menufromsite_saved_locations = array( $menufromsite_saved_locations ); // Backwards-compatibility from previous version.
		}

		if ( count( $menufromsite_locations ) ) {

			$menufromsite_option_count = 1;

			foreach ( $menufromsite_location_keys as $menufromsite_location ) {
				$menufromsite_checked = in_array( $menufromsite_location, $menufromsite_saved_locations, true );

				echo '<input type="checkbox" id="mfs_override_menu_location[' . $menufromsite_option_count . ']" name="mfs_override_menu_location[' . $menufromsite_option_count . ']" value="' . $menufromsite_location . '"' . ( $menufromsite_checked ? ' checked="checked" ' : '' ) . '><label for="mfs_override_menu_location[' . $menufromsite_option_count . ']">' . $menufromsite_location . '</label><br/>';
				++$menufromsite_option_count;
			}
		} else {
			// No menu locations.
			echo '<div class="error"><em>Error: No navigation menus have been registered for this theme. Please view <a href="http://codex.wordpress.org/Function_Reference/register_nav_menu">WordPress\' documentation</a> to learn how to register navigation menus.</em></div>';
		}

		echo '</td></tr>
			<tr>
				<td>';

		submit_button();
		echo '</td></tr>';
		?>
			</tbody>
		</table>
	</form>
	
</div>