<?php
/**
 * Plugin Name:       Multisite Shared Menu
 * Plugin URI:        http://www.bengreeley.com/menufromsite
 * Description:       Allows users in a WordPress multisite network pull in a menu from another site in order to achieve a universal navigation or shared navigation without needing to manually recreate menus. Site is required to be installed on WordPress Multisite environment.
 * Version:           1.2
 * Author:            Ben Greeley
 * Author URI:        http://www.bengreeley.com
 **/

 /*  
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Useful global constants.
define( 'MULTISITE_SHARED_MENU_PLUGIN_VERSION', '2.0' );
define( 'MULTISITE_SHARED_MENU_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MULTISITE_SHARED_MENU_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'MULTISITE_SHARED_MENU_PLUGIN_INC', MULTISITE_SHARED_MENU_PLUGIN_PATH . 'includes/' );
define( 'MULTISITE_SHARED_MENU_PLUGIN_DIST_URL', MULTISITE_SHARED_MENU_PLUGIN_URL . 'dist/' );
define( 'MULTISITE_SHARED_MENU_PLUGIN_DIST_PATH', MULTISITE_SHARED_MENU_PLUGIN_PATH . 'dist/' );

require_once MULTISITE_SHARED_MENU_PLUGIN_PATH . 'vendor/autoload.php';

register_activation_hook( __FILE__, 'activate_menufromsite' );
register_deactivation_hook( __FILE__, 'deactivate_menufromsite' );

// Activation code for plugin to create necessary fields, etc.
function activate_menufromsite() {
	require_once MULTISITE_SHARED_MENU_PLUGIN_INC . 'class-menufromsite-activator.php';
	MultisiteSharedMenu_Activator::activate();
}

/**
 * Undocumented function
 *
 * @return void
 */
function deactivate_menufromsite() {
	require_once MULTISITE_SHARED_MENU_PLUGIN_INC . 'class-menufromsite-deactivator.php';
	MultisiteSharedMenu_Deactivator::deactivate();
}

if ( function_exists( 'is_multisite' ) && is_multisite() ) {
	// Only run this menu if in multisite...
	new MultisiteSharedMenu();
}