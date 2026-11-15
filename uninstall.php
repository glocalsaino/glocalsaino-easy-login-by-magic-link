<?php
/**
 * Uninstall: removes every stored magic link.
 *
 * @package GlocalSaino_Easy_Login_By_Magic_Link
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'glocalsaino_magic_link_links' );
