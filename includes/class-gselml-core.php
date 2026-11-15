<?php
/**
 * Core logic: storing magic links, validating them and logging the user in.
 *
 * @package GlocalSaino_Easy_Login_By_Magic_Link
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Magic link storage and login handling.
 */
class GSELML_Core {

	/**
	 * Option holding every magic link, keyed by token.
	 */
	const OPTION_KEY = 'glocalsaino_magic_link_links';

	/**
	 * Query argument that carries the token in the link.
	 */
	const QUERY_VAR = 'glocalsaino_magic_link';

	/**
	 * Admin page slug (Settings submenu).
	 */
	const PAGE_SLUG = 'glocalsaino-easy-login-by-magic-link';

	/**
	 * Allowed expiry choices, in seconds (0 = never).
	 */
	const EXPIRY_CHOICES = array( 3600, 86400, 604800, 2592000, 0 );

	/**
	 * Allowed maximum-use choices (0 = unlimited).
	 */
	const USES_CHOICES = array( 1, 5, 10, 0 );

	/**
	 * Checks the token on every front-end request and, if valid, logs the
	 * linked user in and redirects to the configured destination.
	 */
	public function maybe_grant_access() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The token itself is the credential; a nonce cannot exist for a link opened from an email.
		$raw_token = isset( $_GET[ self::QUERY_VAR ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) : '';

		if ( '' === $raw_token || is_admin() || ! preg_match( '/^[a-f0-9]{64}$/', $raw_token ) ) {
			return;
		}

		$links = $this->get_links();

		if ( ! isset( $links[ $raw_token ] ) ) {
			return;
		}

		$link = $links[ $raw_token ];

		if ( $this->is_expired( $link ) || $this->is_exhausted( $link ) ) {
			return;
		}

		$user = get_user_by( 'id', (int) $link['user_id'] );
		if ( ! $user instanceof WP_User ) {
			return;
		}

		$links[ $raw_token ]['uses'] = (int) $link['uses'] + 1;
		$links[ $raw_token ]['last'] = time();
		$this->save_links( $links );

		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, false, is_ssl() );
		/** This action is documented in wp-includes/user.php */
		do_action( 'wp_login', $user->user_login, $user );

		/**
		 * Fires after a user has been logged in through a magic link.
		 *
		 * @param WP_User $user Logged-in user.
		 * @param array   $link Link data (user_id, label, redirect_to, created, expires, max_uses, uses).
		 */
		do_action( 'glocalsaino_magic_link_logged_in', $user, $link );

		$redirect_to = ! empty( $link['redirect_to'] ) ? $link['redirect_to'] : home_url( '/' );

		nocache_headers();
		wp_safe_redirect( $redirect_to );
		exit;
	}

	/**
	 * Handles the "Create link" form.
	 */
	public function handle_create() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'glocalsaino-easy-login-by-magic-link' ) );
		}
		check_admin_referer( 'glocalsaino_magic_link_create' );

		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		$user    = get_user_by( 'id', $user_id );

		if ( ! $user instanceof WP_User ) {
			$this->redirect_back( array( 'gselml_notice' => 'no_user' ) );
		}

		$expires_in  = isset( $_POST['expires_in'] ) ? absint( wp_unslash( $_POST['expires_in'] ) ) : 86400;
		$max_uses    = isset( $_POST['max_uses'] ) ? absint( wp_unslash( $_POST['max_uses'] ) ) : 1;
		$label       = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';

		if ( ! in_array( $expires_in, self::EXPIRY_CHOICES, true ) ) {
			$expires_in = 86400;
		}
		if ( ! in_array( $max_uses, self::USES_CHOICES, true ) ) {
			$max_uses = 1;
		}
		// Only same-site destinations: wp_safe_redirect() would drop anything else anyway.
		if ( '' !== $redirect_to && wp_validate_redirect( $redirect_to, '' ) !== $redirect_to ) {
			$this->redirect_back( array( 'gselml_notice' => 'bad_redirect' ) );
		}

		$token = bin2hex( random_bytes( 32 ) );
		$links = $this->get_links();

		$links[ $token ] = array(
			'user_id'     => $user_id,
			'label'       => $label,
			'redirect_to' => $redirect_to,
			'created'     => time(),
			'expires'     => $expires_in ? time() + $expires_in : 0,
			'max_uses'    => $max_uses,
			'uses'        => 0,
			'last'        => 0,
		);

		$this->save_links( $links );

		$this->redirect_back( array( 'gselml_notice' => 'created' ) );
	}

	/**
	 * Handles the "Revoke" button.
	 */
	public function handle_revoke() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'glocalsaino-easy-login-by-magic-link' ) );
		}
		check_admin_referer( 'glocalsaino_magic_link_revoke' );

		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$links = $this->get_links();

		if ( '' !== $token && isset( $links[ $token ] ) ) {
			unset( $links[ $token ] );
			$this->save_links( $links );
		}

		$this->redirect_back( array( 'gselml_notice' => 'revoked' ) );
	}

	/**
	 * All stored links.
	 *
	 * @return array
	 */
	public function get_links() {
		$links = get_option( self::OPTION_KEY, array() );
		return is_array( $links ) ? $links : array();
	}

	/**
	 * Saves the links (not autoloaded: only needed when a link is used or managed).
	 *
	 * @param array $links Links keyed by token.
	 */
	private function save_links( array $links ) {
		update_option( self::OPTION_KEY, $links, false );
	}

	/**
	 * Users offered in the "User" dropdown.
	 *
	 * @return WP_User[]
	 */
	public function get_users() {
		return get_users(
			array(
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'fields'  => array( 'ID', 'display_name', 'user_email' ),
			)
		);
	}

	/**
	 * Public URL of a link.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	public function get_link_url( $token ) {
		return add_query_arg( self::QUERY_VAR, $token, home_url( '/' ) );
	}

	/**
	 * Whether the link is past its expiry date.
	 *
	 * @param array $link Link data.
	 * @return bool
	 */
	public function is_expired( array $link ) {
		return ! empty( $link['expires'] ) && time() > (int) $link['expires'];
	}

	/**
	 * Whether the link has reached its maximum number of uses.
	 *
	 * @param array $link Link data.
	 * @return bool
	 */
	public function is_exhausted( array $link ) {
		return ! empty( $link['max_uses'] ) && (int) $link['uses'] >= (int) $link['max_uses'];
	}

	/**
	 * URL of the plugin's admin page.
	 *
	 * @return string
	 */
	public function admin_page_url() {
		return admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * Redirects to the admin page with a notice and stops.
	 *
	 * @param array $args Query arguments.
	 */
	private function redirect_back( array $args ) {
		wp_safe_redirect( add_query_arg( $args, $this->admin_page_url() ) );
		exit;
	}
}
