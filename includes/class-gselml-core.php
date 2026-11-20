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
	 * Option holding the plugin settings.
	 */
	const SETTINGS_KEY = 'glocalsaino_magic_link_settings';

	/**
	 * Query argument that carries the token in the link.
	 */
	const QUERY_VAR = 'glocalsaino_magic_link';

	/**
	 * Cookie holding the random browser identifier used by device-bound links.
	 */
	const DEVICE_COOKIE = 'glocalsaino_magic_link_device';

	/**
	 * Admin page slug (top-level menu).
	 */
	const PAGE_SLUG = 'glocalsaino-easy-login-by-magic-link';

	/**
	 * Allowed expiry choices, in seconds (0 = never).
	 */
	const EXPIRY_CHOICES = array( 900, 3600, 86400, 604800, 2592000, 0 );

	/**
	 * Allowed maximum-use choices (0 = unlimited).
	 */
	const USES_CHOICES = array( 1, 5, 10, 0 );

	/**
	 * IP restriction modes: any IP, the IP of the first use, or a fixed list.
	 */
	const IP_MODES = array( 'any', 'first', 'list' );

	/**
	 * Device restriction modes: any browser, the browser of the first use, or the
	 * browser that requested the link (self-service and registration links).
	 */
	const DEVICE_MODES = array( 'any', 'first', 'requester' );

	/**
	 * Browser identifier set during this request (cookies are only readable on the next one).
	 *
	 * @var string
	 */
	private $device_id = '';

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'request_expiry'      => 900,
			'request_confirm'     => 1,
			'request_same_device' => 0,
			'limit_email'         => 3,
			'limit_ip'            => 10,
			'blocked_roles'       => array( 'administrator' ),
			'register_checkbox'   => 1,
			'register_expiry'     => 86400,
			'role_redirects'      => array(),
		);
	}

	/**
	 * Current settings merged with the defaults.
	 *
	 * @return array
	 */
	public function get_settings() {
		$saved = get_option( self::SETTINGS_KEY, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::default_settings() );
	}

	/**
	 * Handles magic-link requests on the front end.
	 *
	 * GET  ?glocalsaino_magic_link=TOKEN  → confirmation page (or direct login when
	 *                                       the link was created without it).
	 * POST glocalsaino_magic_link=TOKEN  → logs the user in (button on that page).
	 *
	 * Opening the link never uses it up when the confirmation page is on, so email
	 * security scanners that pre-open links cannot spend single-use links.
	 */
	public function maybe_grant_access() {
		if ( is_admin() ) {
			return;
		}

		$is_post = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'];

		if ( $is_post && isset( $_POST[ self::QUERY_VAR ] ) ) {
			$token = sanitize_text_field( wp_unslash( $_POST[ self::QUERY_VAR ] ) );
			$nonce = isset( $_POST['_gselml_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_gselml_nonce'] ) ) : '';
			$this->handle_token( $token, (bool) wp_verify_nonce( $nonce, 'glocalsaino_magic_link_login_' . $token ) );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The token itself is the credential; a nonce cannot exist for a link opened from an email.
		$token = isset( $_GET[ self::QUERY_VAR ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) : '';
		if ( '' !== $token ) {
			$this->handle_token( $token, false );
		}
	}

	/**
	 * Validates a token and shows the right page or logs the user in.
	 *
	 * @param string $token     Token from the request.
	 * @param bool   $confirmed True when it comes from the confirmation button with a valid nonce.
	 */
	private function handle_token( $token, $confirmed ) {
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return;
		}

		$links = $this->get_links();
		$link  = isset( $links[ $token ] ) ? $this->normalize_link( $links[ $token ] ) : null;
		$user  = $link ? get_user_by( 'id', (int) $link['user_id'] ) : false;

		if ( ! $link || ! $user instanceof WP_User || $this->is_expired( $link ) || $this->is_exhausted( $link ) ) {
			$this->render_invalid_page();
		}

		$restriction = $this->restriction_error( $link );
		if ( '' !== $restriction ) {
			$this->render_restricted_page( $restriction );
		}

		if ( ! $confirmed && $this->needs_confirmation( $link ) ) {
			$this->render_confirm_page( $token, $user );
		}

		$link['uses'] = (int) $link['uses'] + 1;
		$link['last'] = time();

		// "First use" restrictions are fixed now, on the use that actually logs in.
		if ( 'first' === $link['ip_mode'] && '' === $link['bound_ip'] ) {
			$link['bound_ip'] = $this->client_ip();
		}
		if ( 'first' === $link['device_mode'] && '' === $link['device_hash'] ) {
			$link['device_hash'] = $this->device_hash( $this->ensure_device_id() );
		}

		$links[ $token ] = $link;
		$this->save_links( $links );

		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, false, is_ssl() );
		/** This action is documented in wp-includes/user.php */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, fired as wp_signon() does so other plugins see a normal login.
		do_action( 'wp_login', $user->user_login, $user );

		/**
		 * Fires after a user has been logged in through a magic link.
		 *
		 * @param WP_User $user Logged-in user.
		 * @param array   $link Link data (user_id, label, redirect_to, created, expires, max_uses,
		 *                      uses, confirm, source, ip_mode, ips, bound_ip, device_mode).
		 */
		do_action( 'gselml_logged_in', $user, $link );

		nocache_headers();
		wp_safe_redirect( $this->redirect_for( $user, $link ) );
		exit;
	}

	/**
	 * Fills in the fields that links created by older versions do not have.
	 *
	 * @param array $link Stored link.
	 * @return array
	 */
	public function normalize_link( array $link ) {
		return wp_parse_args(
			$link,
			array(
				'user_id'     => 0,
				'label'       => '',
				'redirect_to' => '',
				'created'     => 0,
				'expires'     => 0,
				'max_uses'    => 1,
				'uses'        => 0,
				'last'        => 0,
				'confirm'     => true,
				'source'      => 'admin',
				'ip_mode'     => 'any',
				'ips'         => array(),
				'bound_ip'    => '',
				'device_mode' => 'any',
				'device_hash' => '',
			)
		);
	}

	/**
	 * Checks the IP and device restrictions of a link.
	 *
	 * @param array $link Normalized link.
	 * @return string '' when allowed, 'ip' or 'device' otherwise.
	 */
	public function restriction_error( array $link ) {
		$ip = $this->client_ip();

		if ( 'list' === $link['ip_mode'] && ! $this->ip_in_list( $ip, (array) $link['ips'] ) ) {
			return 'ip';
		}
		if ( 'first' === $link['ip_mode'] && '' !== $link['bound_ip'] && ! $this->ip_in_list( $ip, array( $link['bound_ip'] ) ) ) {
			return 'ip';
		}

		if ( '' !== $link['device_hash'] ) {
			$device_id = $this->current_device_id();
			if ( '' === $device_id || ! hash_equals( (string) $link['device_hash'], $this->device_hash( $device_id ) ) ) {
				return 'device';
			}
		}

		return '';
	}

	/**
	 * Where to send the user after logging in: the link's own page, then the page
	 * set for the user's role, then the home page.
	 *
	 * @param WP_User $user User.
	 * @param array   $link Link data.
	 * @return string
	 */
	public function redirect_for( WP_User $user, array $link ) {
		$redirect_to = '';

		if ( ! empty( $link['redirect_to'] ) ) {
			$redirect_to = (string) $link['redirect_to'];
		} else {
			$settings = $this->get_settings();
			$by_role  = (array) $settings['role_redirects'];
			foreach ( (array) $user->roles as $role ) {
				if ( ! empty( $by_role[ $role ] ) ) {
					$redirect_to = (string) $by_role[ $role ];
					break;
				}
			}
		}

		if ( '' === $redirect_to ) {
			$redirect_to = home_url( '/' );
		}

		/**
		 * Filters the page a user lands on after a magic-link login.
		 *
		 * @param string  $redirect_to Same-site URL.
		 * @param WP_User $user        User being logged in.
		 * @param array   $link        Link data.
		 */
		return (string) apply_filters( 'gselml_redirect_to', $redirect_to, $user, $link );
	}

	/**
	 * Creates and stores a magic link.
	 *
	 * @param int   $user_id User ID.
	 * @param array $args    Link options (see the defaults below).
	 * @return string Token.
	 */
	public function create_link( $user_id, array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'label'       => '',
				'redirect_to' => '',
				'expires_in'  => 86400,
				'max_uses'    => 1,
				'confirm'     => true,
				'source'      => 'admin',
				'ip_mode'     => 'any',
				'ips'         => array(),
				'device_mode' => 'any',
				'device_hash' => '',
			)
		);

		$links = $this->purge_links( $this->get_links() );

		// Emailed links: only the newest unused one per user stays valid.
		if ( 'admin' !== $args['source'] ) {
			foreach ( $links as $token => $link ) {
				$link = $this->normalize_link( $link );
				if ( 'admin' !== $link['source'] && (int) $link['user_id'] === (int) $user_id && 0 === (int) $link['uses'] ) {
					unset( $links[ $token ] );
				}
			}
		}

		$token = bin2hex( random_bytes( 32 ) );

		$links[ $token ] = array(
			'user_id'     => (int) $user_id,
			'label'       => (string) $args['label'],
			'redirect_to' => (string) $args['redirect_to'],
			'created'     => time(),
			'expires'     => $args['expires_in'] ? time() + (int) $args['expires_in'] : 0,
			'max_uses'    => (int) $args['max_uses'],
			'uses'        => 0,
			'last'        => 0,
			'confirm'     => (bool) $args['confirm'],
			'source'      => (string) $args['source'],
			'ip_mode'     => in_array( $args['ip_mode'], self::IP_MODES, true ) ? $args['ip_mode'] : 'any',
			'ips'         => array_values( (array) $args['ips'] ),
			'bound_ip'    => '',
			'device_mode' => in_array( $args['device_mode'], self::DEVICE_MODES, true ) ? $args['device_mode'] : 'any',
			'device_hash' => (string) $args['device_hash'],
		);

		$this->save_links( $links );

		return $token;
	}

	/**
	 * Drops expired or used-up links that were sent by email (admin links stay
	 * listed until revoked, so the admin can see their status).
	 *
	 * @param array $links Links keyed by token.
	 * @return array
	 */
	private function purge_links( array $links ) {
		foreach ( $links as $token => $link ) {
			$link = $this->normalize_link( $link );
			if ( 'admin' !== $link['source'] && ( $this->is_expired( $link ) || $this->is_exhausted( $link ) ) ) {
				unset( $links[ $token ] );
			}
		}
		return $links;
	}

	/**
	 * Whether the link shows the "Log in" button page before logging in.
	 * Links stored without the setting default to the safe behaviour.
	 *
	 * @param array $link Link data.
	 * @return bool
	 */
	public function needs_confirmation( array $link ) {
		return ! isset( $link['confirm'] ) || ! empty( $link['confirm'] );
	}

	/**
	 * Visitor IP address. Uses REMOTE_ADDR only, which cannot be spoofed; sites
	 * behind a reverse proxy or CDN can supply the real client IP with the
	 * `gselml_client_ip` filter.
	 *
	 * @return string
	 */
	public function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip = filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';

		/**
		 * Filters the client IP used by IP-restricted links and rate limits.
		 *
		 * @param string $ip IP address from REMOTE_ADDR.
		 */
		$filtered = (string) apply_filters( 'gselml_client_ip', $ip );

		return filter_var( $filtered, FILTER_VALIDATE_IP ) ? $filtered : $ip;
	}

	/**
	 * Whether an IP matches any entry of a list of IPs and CIDR ranges.
	 *
	 * @param string $ip   IP address.
	 * @param array  $list IPs or ranges such as 203.0.113.0/24 or 2001:db8::/32.
	 * @return bool
	 */
	public function ip_in_list( $ip, array $list ) {
		$ip_bin = filter_var( $ip, FILTER_VALIDATE_IP ) ? inet_pton( $ip ) : false;
		if ( false === $ip_bin ) {
			return false;
		}

		foreach ( $list as $entry ) {
			$parts  = explode( '/', (string) $entry, 2 );
			$subnet = filter_var( $parts[0], FILTER_VALIDATE_IP ) ? inet_pton( $parts[0] ) : false;
			if ( false === $subnet || strlen( $subnet ) !== strlen( $ip_bin ) ) {
				continue;
			}

			$bits = isset( $parts[1] ) ? (int) $parts[1] : strlen( $ip_bin ) * 8;
			$full = intdiv( $bits, 8 );
			$rest = $bits % 8;

			if ( substr( $ip_bin, 0, $full ) !== substr( $subnet, 0, $full ) ) {
				continue;
			}
			if ( $rest ) {
				$mask = ( 0xff << ( 8 - $rest ) ) & 0xff;
				if ( ( ord( $ip_bin[ $full ] ) & $mask ) !== ( ord( $subnet[ $full ] ) & $mask ) ) {
					continue;
				}
			}
			return true;
		}

		return false;
	}

	/**
	 * Parses a free-text list of IPs and CIDR ranges.
	 *
	 * @param string $text Text with one entry per line (commas and spaces also work).
	 * @return array|false Valid entries, or false if any entry is invalid.
	 */
	public function parse_ip_list( $text ) {
		$entries = preg_split( '/[\s,]+/', (string) $text, -1, PREG_SPLIT_NO_EMPTY );
		$valid   = array();

		foreach ( $entries as $entry ) {
			$parts = explode( '/', $entry, 2 );
			if ( ! filter_var( $parts[0], FILTER_VALIDATE_IP ) ) {
				return false;
			}
			if ( isset( $parts[1] ) ) {
				$max = filter_var( $parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ? 128 : 32;
				if ( ! ctype_digit( $parts[1] ) || (int) $parts[1] > $max ) {
					return false;
				}
			}
			$valid[] = $entry;
		}

		return array_values( array_unique( $valid ) );
	}

	/**
	 * Browser identifier sent by the visitor, if any.
	 *
	 * @return string
	 */
	private function current_device_id() {
		if ( '' !== $this->device_id ) {
			return $this->device_id;
		}
		$cookie = isset( $_COOKIE[ self::DEVICE_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::DEVICE_COOKIE ] ) ) : '';
		return preg_match( '/^[a-f0-9]{64}$/', $cookie ) ? $cookie : '';
	}

	/**
	 * Returns the browser identifier, creating the cookie when it does not exist.
	 * The cookie holds a random value only; the link stores a keyed hash of it.
	 *
	 * @return string
	 */
	public function ensure_device_id() {
		$device_id = $this->current_device_id();
		if ( '' !== $device_id ) {
			return $device_id;
		}

		$this->device_id = bin2hex( random_bytes( 32 ) );

		if ( ! headers_sent() ) {
			setcookie(
				self::DEVICE_COOKIE,
				$this->device_id,
				array(
					'expires'  => time() + YEAR_IN_SECONDS,
					'path'     => COOKIEPATH ? COOKIEPATH : '/',
					'domain'   => COOKIE_DOMAIN ? (string) COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}

		return $this->device_id;
	}

	/**
	 * Keyed hash of a browser identifier, as stored in the link.
	 *
	 * @param string $device_id Browser identifier.
	 * @return string
	 */
	public function device_hash( $device_id ) {
		return hash_hmac( 'sha256', $device_id, wp_salt( 'auth' ) );
	}

	/**
	 * Human label for an expiry choice.
	 *
	 * @param int $seconds Seconds (0 = never).
	 * @return string
	 */
	public static function expiry_label( $seconds ) {
		$labels = array(
			900     => __( '15 minutes', 'glocalsaino-easy-login-by-magic-link' ),
			3600    => __( '1 hour', 'glocalsaino-easy-login-by-magic-link' ),
			86400   => __( '1 day', 'glocalsaino-easy-login-by-magic-link' ),
			604800  => __( '7 days', 'glocalsaino-easy-login-by-magic-link' ),
			2592000 => __( '30 days', 'glocalsaino-easy-login-by-magic-link' ),
			0       => __( 'Never', 'glocalsaino-easy-login-by-magic-link' ),
		);
		return isset( $labels[ $seconds ] ) ? $labels[ $seconds ] : human_time_diff( 0, (int) $seconds );
	}

	/**
	 * Sends the headers shared by the front-end pages and prints the page.
	 *
	 * @param string $title   Page title.
	 * @param string $content Inner HTML, already escaped.
	 */
	private function render_page( $title, $content ) {
		nocache_headers();
		if ( ! headers_sent() ) {
			// Keeps the token out of the Referer header of any request made from the page.
			header( 'Referrer-Policy: no-referrer' );
		}

		$allowed = array(
			'h1'     => array(),
			'p'      => array( 'class' => true ),
			'strong' => array(),
			'a'      => array(
				'href'  => true,
				'class' => true,
			),
			'form'   => array(
				'method' => true,
				'action' => true,
			),
			'input'  => array(
				'type'  => true,
				'name'  => true,
				'value' => true,
			),
			'button' => array(
				'type'  => true,
				'class' => true,
			),
		);

		wp_die(
			wp_kses( $content, $allowed ),
			esc_html( $title ),
			array( 'response' => 200 )
		);
	}

	/**
	 * Page with the "Log in" button.
	 *
	 * @param string  $token Token.
	 * @param WP_User $user  Linked user.
	 */
	private function render_confirm_page( $token, WP_User $user ) {
		$site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$title = __( 'Log in', 'glocalsaino-easy-login-by-magic-link' );

		$html  = '<h1>' . esc_html( $site ) . '</h1>';
		$html .= '<p>' . sprintf(
			/* translators: %s: user display name. */
			esc_html__( 'Log in as %s by clicking the button below.', 'glocalsaino-easy-login-by-magic-link' ),
			'<strong>' . esc_html( $user->display_name ) . '</strong>'
		) . '</p>';
		$html .= '<form method="post" action="' . esc_url( home_url( '/' ) ) . '">';
		$html .= '<input type="hidden" name="' . esc_attr( self::QUERY_VAR ) . '" value="' . esc_attr( $token ) . '" />';
		$html .= '<input type="hidden" name="_gselml_nonce" value="' . esc_attr( wp_create_nonce( 'glocalsaino_magic_link_login_' . $token ) ) . '" />';
		$html .= '<p><button type="submit" class="button button-large">' . esc_html( $title ) . '</button></p>';
		$html .= '</form>';

		$this->render_page( $title, $html );
	}

	/**
	 * Page shown for unknown, expired or used-up links.
	 */
	private function render_invalid_page() {
		$title = __( 'Login link not valid', 'glocalsaino-easy-login-by-magic-link' );

		$html  = '<h1>' . esc_html( $title ) . '</h1>';
		$html .= '<p>' . esc_html__( 'This login link has expired, has already been used or has been revoked. Request a new one or ask the site administrator.', 'glocalsaino-easy-login-by-magic-link' ) . '</p>';
		$html .= '<p><a href="' . esc_url( wp_login_url() ) . '">' . esc_html__( 'Go to the login page', 'glocalsaino-easy-login-by-magic-link' ) . '</a></p>';

		$this->render_page( $title, $html );
	}

	/**
	 * Page shown when a valid link is opened from a disallowed IP or browser.
	 *
	 * @param string $reason 'ip' or 'device'.
	 */
	private function render_restricted_page( $reason ) {
		$title = __( 'Login link not valid here', 'glocalsaino-easy-login-by-magic-link' );

		$message = 'ip' === $reason
			? __( 'This login link is restricted to certain internet connections (IP addresses), and it cannot be used from the one you are using now.', 'glocalsaino-easy-login-by-magic-link' )
			: __( 'This login link only works in the browser where it was requested or first used. Open it in that browser, or request a new link from this one.', 'glocalsaino-easy-login-by-magic-link' );

		$html  = '<h1>' . esc_html( $title ) . '</h1>';
		$html .= '<p>' . esc_html( $message ) . '</p>';
		$html .= '<p><a href="' . esc_url( wp_login_url() ) . '">' . esc_html__( 'Go to the login page', 'glocalsaino-easy-login-by-magic-link' ) . '</a></p>';

		$this->render_page( $title, $html );
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
		$confirm     = ! empty( $_POST['confirm'] );
		$ip_mode     = isset( $_POST['ip_mode'] ) ? sanitize_key( wp_unslash( $_POST['ip_mode'] ) ) : 'any';
		$ips_text    = isset( $_POST['ips'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ips'] ) ) : '';
		$device_mode = isset( $_POST['device_mode'] ) ? sanitize_key( wp_unslash( $_POST['device_mode'] ) ) : 'any';

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

		$ips = array();
		if ( ! in_array( $ip_mode, self::IP_MODES, true ) ) {
			$ip_mode = 'any';
		}
		if ( 'list' === $ip_mode ) {
			$ips = $this->parse_ip_list( $ips_text );
			if ( empty( $ips ) ) {
				$this->redirect_back( array( 'gselml_notice' => 'bad_ip' ) );
			}
		}
		// "requester" only makes sense for links requested from a browser.
		if ( ! in_array( $device_mode, array( 'any', 'first' ), true ) ) {
			$device_mode = 'any';
		}

		$this->create_link(
			$user_id,
			array(
				'label'       => $label,
				'redirect_to' => $redirect_to,
				'expires_in'  => $expires_in,
				'max_uses'    => $max_uses,
				'confirm'     => $confirm,
				'source'      => 'admin',
				'ip_mode'     => $ip_mode,
				'ips'         => $ips,
				'device_mode' => $device_mode,
			)
		);

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
	 * Whether users with this role may receive links by email (request form and
	 * registration checkbox).
	 *
	 * @param WP_User $user User.
	 * @return bool
	 */
	public function user_can_receive_emailed_links( WP_User $user ) {
		$settings = $this->get_settings();
		$blocked  = (array) $settings['blocked_roles'];
		$allowed  = ! array_intersect( (array) $user->roles, $blocked );

		/**
		 * Filters whether a user may receive magic links by email.
		 *
		 * @param bool    $allowed Whether the user's roles allow it.
		 * @param WP_User $user    User.
		 */
		return (bool) apply_filters( 'gselml_user_can_receive_emailed_links', $allowed, $user );
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
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
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
