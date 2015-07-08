=== Multisite Shared Menu ===
Contributors: ben.greeley, Third Boxcar
Tags: menus, multisite, navigation
Requires at least: 3.9
Tested up to: 4.1
Stable tag: 1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Use navigation menus from another multisite blog on the same network to achieve a universal common navigation area.

== Description ==
Allows users in a WordPress multisite network pull in menus from a main site in order to achieve universal navigation or shared navigation without needing to manually recreate menus. 

Plugin is intended for WordPress Multisite environment for plugin to function properly and use the same theme to ensure menu name compatibility.

== Installation ==
1. Copy the folder into your /wp-content/plugins directory.
1. Activate for the sites you wish to use plugin on. NOTE: Should not be activated on master menu site, and all sites sharing menus should use the same theme.
1. If your master/main site does not yet have a menu, set up a menu to use as a “master”. 
1. To use this menu on other sites, browse to the site you wish to use the menu on. 
1. Navigate to ‘Shared Menu Settings’ under the ‘Appearance’ menu. 
1. Select the source site that contains the menu you wish to use and select the menu location(s) to pull in from your primary site.
1. Click ‘save changes’ to save your changes.

== Screenshots ==
1. Multisite Shared Menu options menu

== Changelog ==
<h3>1.2</h3>
Multiple menu selection! You can now include one or more menus from your master menu site.

<h3>1.11</h3>
Documentation improvements and screenshots.

<h3>1.1</h3>
Fixed bugs relating to registered menu locations.

<h3>1.0</h3>
Initial Release
