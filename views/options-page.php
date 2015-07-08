<div class="wrap">
			
	<h2>Multisite Shared Menu Settings</h2>
	<p>Select the site containing the menu(s) you wish to display and select which menus to bring in.</p>
	<p>NOTE: both sites should use the same theme to ensure menu location compatibility.</p>
		
	<form method="post" action="options.php">	    
		<table class="form-table">
			<tbody>
		<?php
			settings_fields( 'menufromsite-group' );
			do_settings_sections( 'menufromsite-group' );	
			
			// Output dropdown menu of available sites...
			$blogList = wp_get_sites();
			
			echo '<tr>
					<th scope="row"><label for="mfs_override_site_id">Source Site:</label></th>';
			
			echo '<td>
					<select name="mfs_override_site_id" id="mfs_override_site_id">';
			
			
			echo '<option value="">-- Select --</option>';
			foreach( $blogList as $blogTemp ) {
				if( $blogTemp['blog_id'] != get_current_blog_id() ) {
					
					echo '<option value="'.$blogTemp['blog_id'].'"';
					
					if( esc_attr( get_option('mfs_override_site_id') ) == $blogTemp['blog_id'] ) {
						echo ' selected ';
					}
					
					echo '>'.$blogTemp['domain'].'</option>';
					
				}
			}
			
			echo '</select>
				</td>
			</tr>';
			
			// Output available theme menu locations...
			echo '<tr>
			<th scope="row"><label for="mfs_override_menu_location">Menu Location:</span></th>
			<td>';
			
			
			$locations = get_registered_nav_menus();
			$locationKeys = array_keys( $locations );

			if( count($locations) ) {
				$option_count = 1;
				foreach ($locationKeys as $curLocation ) {
					if ( in_array ( $curLocation, get_option('mfs_override_menu_location') ) ) {
						$checked = true;
					}
					else {
						$checked = false;
					}
					
					echo '<input type="checkbox" name="mfs_override_menu_location['.$option_count.']" value="'. $curLocation .'"' . ($checked == true ? ' checked="checked" ' : '' ) . '><label for="mfs_override_menu_location['.$option_count.']">' . $curLocation . '</label><br/>';
					$option_count ++;
				}
			}
			else {
				// No menu locations
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