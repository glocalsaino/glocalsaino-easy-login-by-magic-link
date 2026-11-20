<?php
/**
 * Plugin Name:       GlocalSaino Easy Login by Magic Link
 * Plugin URI:        https://glocalsaino.com/easyloginbymagiclink/
 * Description:       Passwordless login links that only work where they should: lock them to an IP address or a browser, let users request them by email or at registration, and send each role to its own page.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Glocal Saino
 * Author URI:        https://glocalsaino.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       glocalsaino-easy-login-by-magic-link
 * Domain Path:       /languages
 *
 * @package GlocalSaino_Easy_Login_By_Magic_Link
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GSELML_VERSION', '1.0.0' );
define( 'GSELML_FILE', __FILE__ );
define( 'GSELML_PATH', plugin_dir_path( __FILE__ ) );
define( 'GSELML_URL', plugin_dir_url( __FILE__ ) );

require_once GSELML_PATH . 'includes/class-gselml-core.php';
require_once GSELML_PATH . 'includes/class-gselml-admin.php';
require_once GSELML_PATH . 'includes/class-gselml-settings.php';
require_once GSELML_PATH . 'includes/class-gselml-requests.php';

$gselml_core     = new GSELML_Core();
$gselml_admin    = new GSELML_Admin( $gselml_core );
$gselml_settings = new GSELML_Settings( $gselml_core );
$gselml_requests = new GSELML_Requests( $gselml_core );

$gselml_admin->set_settings( $gselml_settings );
$gselml_requests->register();

add_action( 'init', array( $gselml_core, 'maybe_grant_access' ) );
add_action( 'admin_post_glocalsaino_magic_link_create', array( $gselml_core, 'handle_create' ) );
add_action( 'admin_post_glocalsaino_magic_link_revoke', array( $gselml_core, 'handle_revoke' ) );
add_action( 'admin_menu', array( $gselml_admin, 'register_menu' ) );
add_action( 'admin_menu', array( $gselml_settings, 'register_menu' ) );
add_action( 'admin_init', array( $gselml_settings, 'register_setting' ) );
add_action( 'admin_enqueue_scripts', array( $gselml_admin, 'enqueue_assets' ) );
add_filter( 'plugin_action_links_' . plugin_basename( GSELML_FILE ), array( $gselml_admin, 'action_links' ) );
