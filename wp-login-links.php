<?php
/**
 * Plugin Name:       WP Login Links
 * Plugin URI:        https://glocalsaino.com
 * Description:       Genera enlaces firmados que autentican a un usuario de WordPress automáticamente, sin contraseña. Configura caducidad, límite de usos y URL de destino por enlace.
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            RafaMM-GlocalSaino
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpll
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPLL_VERSION',    '1.0.0' );
define( 'WPLL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPLL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WPLL_PLUGIN_DIR . 'includes/class-wpll-core.php';
require_once WPLL_PLUGIN_DIR . 'includes/class-wpll-admin.php';

$wpll_core  = new WPLL_Core();
$wpll_admin = new WPLL_Admin();

add_action( 'init',                   [ $wpll_core,  'maybe_grant_access' ] );
add_action( 'admin_menu',             [ $wpll_admin, 'register_menu' ] );
add_action( 'admin_enqueue_scripts',  [ $wpll_admin, 'enqueue_assets' ] );
add_action( 'admin_post_wpll_create', [ $wpll_core,  'handle_create' ] );
add_action( 'admin_post_wpll_revoke', [ $wpll_core,  'handle_revoke' ] );
