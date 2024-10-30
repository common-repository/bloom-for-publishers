<?php
/*
Plugin Name: Bloom for Publishers
Plugin URI: https://wordpress.org/plugins/bloom-for-publishers/
Description: Geotag your posts to enable local search and other hyperlocal experiences for your readers.
Version: 1.7.8
Requires at least: 5.2
Requires PHP: 5.6
Author: Bloom Labs
Author URI: https://www.bloom.li
License: GPL v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: bloom-for-publishers
*/

// Include additional functions
include( plugin_dir_path( __FILE__ ) . 'lib/lib.php' );
include( plugin_dir_path( __FILE__ ) . 'post/post.php' );
include( plugin_dir_path( __FILE__ ) . 'search/search.php' );
include( plugin_dir_path( __FILE__ ) . 'admin/admin-settings.php' );
include( plugin_dir_path( __FILE__ ) . 'admin/admin-post.php' );
