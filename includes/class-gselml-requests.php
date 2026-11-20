<?php
/**
 * Self-service links: the [glocalsaino_magic_link_form] shortcode and the
 * checkbox on the WordPress registration form.
 *
 * @package GlocalSaino_Easy_Login_By_Magic_Link
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lets visitors ask for a magic link by email.
 */
class GSELML_Requests {

	/**
	 * Shortcode tag.
	 */
	const SHORTCODE = 'glocalsaino_magic_link_form';

	/**
	 * Query argument carrying the result of a request after the redirect.
	 */
	const RESULT_VAR = 'gselml_request';

	/**
	 * Core instance.
	 *
	 * @var GSELML_Core
	 */
	private $core;

	/**
	 * Links created during this registration request, keyed by user ID.
	 *
	 * @var array
	 */
	private $registration_links = array();

	/**
	 * Constructor.
	 *
	 * @param GSELML_Core $core Core instance.
	 */
	public function __construct( GSELML_Core $core ) {
		$this->core = $core;
	}

	/**
	 * Hooks everything up.
	 */
	public function register() {
		add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
		add_action( 'init', array( $this, 'maybe_handle_request' ) );
		add_action( 'register_form', array( $this, 'render_register_checkbox' ) );
		// Before core sends the new-user email (wp_send_new_user_notifications, priority 10).
		add_action( 'register_new_user', array( $this, 'handle_registration' ), 5 );
		add_filter( 'wp_new_user_notification_email', array( $this, 'add_link_to_new_user_email' ), 10, 2 );
	}

	/* ---------------------------------------------------------------------
	 * Request form (shortcode)
	 * ------------------------------------------------------------------- */

	/**
	 * Renders the request form.
	 *
	 * Attributes: redirect – same-site URL to land on after logging in
	 * (default: the page set for the user's role, then the home page).
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'redirect' => '' ), $atts, self::SHORTCODE );

		if ( is_user_logged_in() ) {
			return '<div class="gselml-request"><p>' . esc_html__( 'You are already logged in.', 'glocalsaino-easy-login-by-magic-link' ) . '</p></div>';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only selects which message to show after the nonce-checked request.
		$result = isset( $_GET[ self::RESULT_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESULT_VAR ] ) ) : '';

		$messages = array(
			'sent'    => __( 'If an account exists for this email address, you will receive a link to log in shortly. Check your inbox and spam folder.', 'glocalsaino-easy-login-by-magic-link' ),
			'limited' => __( 'Too many requests. Please wait a while before trying again.', 'glocalsaino-easy-login-by-magic-link' ),
			'invalid' => __( 'Please enter a valid email address.', 'glocalsaino-easy-login-by-magic-link' ),
			'expired' => __( 'The form has expired. Please reload the page and try again.', 'glocalsaino-easy-login-by-magic-link' ),
		);

		$return = is_singular() ? get_permalink() : home_url( '/' );
		$id     = wp_unique_id( 'gselml-email-' );

		ob_start();
		?>
		<div class="gselml-request" id="gselml-request">
			<?php if ( isset( $messages[ $result ] ) ) : ?>
				<p class="gselml-request__message gselml-request__message--<?php echo esc_attr( $result ); ?>" role="status"><?php echo esc_html( $messages[ $result ] ); ?></p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( $return ); ?>" class="gselml-request__form">
				<p>
					<label for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Email address', 'glocalsaino-easy-login-by-magic-link' ); ?></label><br />
					<input type="email" id="<?php echo esc_attr( $id ); ?>" name="gselml_request_email" required autocomplete="email" />
				</p>
				<p class="gselml-request__hp" aria-hidden="true" style="position:absolute;left:-9999px;">
					<label><?php esc_html_e( 'Leave this field empty', 'glocalsaino-easy-login-by-magic-link' ); ?> <input type="text" name="gselml_website" value="" tabindex="-1" autocomplete="off" /></label>
				</p>
				<input type="hidden" name="gselml_return" value="<?php echo esc_url( $return ); ?>" />
				<input type="hidden" name="gselml_redirect" value="<?php echo esc_url( $atts['redirect'] ); ?>" />
				<?php wp_nonce_field( 'glocalsaino_magic_link_request', '_gselml_request_nonce' ); ?>
				<p><button type="submit"><?php esc_html_e( 'Email me a login link', 'glocalsaino-easy-login-by-magic-link' ); ?></button></p>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Processes a submitted request form, then redirects back (POST/redirect/GET).
	 *
	 * The visitor always sees the same message whether the address belongs to
	 * an account or not, so the form cannot be used to find out who is registered.
	 */
	public function maybe_handle_request() {
		if ( is_admin() || ! isset( $_POST['gselml_request_email'] ) ) {
			return;
		}

		$return = isset( $_POST['gselml_return'] ) ? esc_url_raw( wp_unslash( $_POST['gselml_return'] ) ) : '';
		$return = wp_validate_redirect( $return, home_url( '/' ) );

		$nonce = isset( $_POST['_gselml_request_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_gselml_request_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'glocalsaino_magic_link_request' ) ) {
			$this->back( $return, 'expired' );
		}

		// Bots fill in the hidden field: pretend success and do nothing.
		if ( ! empty( $_POST['gselml_website'] ) ) {
			$this->back( $return, 'sent' );
		}

		$email = sanitize_email( wp_unslash( $_POST['gselml_request_email'] ) );
		if ( ! is_email( $email ) ) {
			$this->back( $return, 'invalid' );
		}

		if ( ! $this->within_limits( $email ) ) {
			$this->back( $return, 'limited' );
		}

		$redirect_to = isset( $_POST['gselml_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['gselml_redirect'] ) ) : '';
		if ( '' !== $redirect_to && wp_validate_redirect( $redirect_to, '' ) !== $redirect_to ) {
			$redirect_to = '';
		}

		$settings    = $this->core->get_settings();
		$same_device = ! empty( $settings['request_same_device'] );

		// Set the browser cookie for every request, so its presence reveals nothing.
		$device_hash = $same_device ? $this->core->device_hash( $this->core->ensure_device_id() ) : '';

		$user = get_user_by( 'email', $email );
		if ( $user instanceof WP_User && $this->core->user_can_receive_emailed_links( $user ) ) {
			$token = $this->core->create_link(
				$user->ID,
				array(
					'label'       => __( 'Requested by email', 'glocalsaino-easy-login-by-magic-link' ),
					'redirect_to' => $redirect_to,
					'expires_in'  => (int) $settings['request_expiry'],
					'max_uses'    => 1,
					'confirm'     => ! empty( $settings['request_confirm'] ),
					'source'      => 'request',
					'device_mode' => $same_device ? 'requester' : 'any',
					'device_hash' => $device_hash,
				)
			);
			$this->send_link_email( $user, $token, (int) $settings['request_expiry'], $same_device );
		}

		$this->back( $return, 'sent' );
	}

	/**
	 * Counts a request against the per-email and per-IP limits.
	 *
	 * @param string $email Requested email address.
	 * @return bool False when a limit has been reached.
	 */
	private function within_limits( $email ) {
		$settings = $this->core->get_settings();
		$checks   = array(
			'gselml_rl_e_' . md5( strtolower( $email ) ) => (int) $settings['limit_email'],
			'gselml_rl_i_' . md5( $this->core->client_ip() ) => (int) $settings['limit_ip'],
		);

		foreach ( $checks as $key => $limit ) {
			$entry = get_transient( $key );
			if ( is_array( $entry ) && (int) $entry['count'] >= $limit ) {
				return false;
			}
		}

		foreach ( $checks as $key => $limit ) {
			$entry = get_transient( $key );
			if ( ! is_array( $entry ) ) {
				$entry = array(
					'count' => 0,
					'start' => time(),
				);
			}
			++$entry['count'];
			$ttl = max( 1, HOUR_IN_SECONDS - ( time() - (int) $entry['start'] ) );
			set_transient( $key, $entry, $ttl );
		}

		return true;
	}

	/**
	 * Sends the login link to the user.
	 *
	 * @param WP_User $user        Recipient.
	 * @param string  $token       Token.
	 * @param int     $expires_in  Lifetime in seconds.
	 * @param bool    $same_device Whether the link only works in the requesting browser.
	 */
	private function send_link_email( WP_User $user, $token, $expires_in, $same_device ) {
		$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$url      = $this->core->get_link_url( $token );

		/* translators: %s: site title. */
		$subject = sprintf( __( '[%s] Your login link', 'glocalsaino-easy-login-by-magic-link' ), $blogname );

		/* translators: %s: user display name. */
		$message  = sprintf( __( 'Hi %s,', 'glocalsaino-easy-login-by-magic-link' ), $user->display_name ) . "\r\n\r\n";
		/* translators: %s: site title. */
		$message .= sprintf( __( 'Use this link to log in to %s:', 'glocalsaino-easy-login-by-magic-link' ), $blogname ) . "\r\n\r\n";
		$message .= $url . "\r\n\r\n";
		/* translators: %s: time span such as "15 minutes" or "1 day". */
		$message .= sprintf( __( 'The link works once and expires in %s.', 'glocalsaino-easy-login-by-magic-link' ), GSELML_Core::expiry_label( $expires_in ) ) . "\r\n";
		if ( $same_device ) {
			$message .= __( 'Open it in the same browser where you requested it.', 'glocalsaino-easy-login-by-magic-link' ) . "\r\n";
		}
		$message .= "\r\n" . __( 'If you did not ask for this link, you can safely ignore this email.', 'glocalsaino-easy-login-by-magic-link' ) . "\r\n";

		$email = array(
			'to'      => $user->user_email,
			'subject' => $subject,
			'message' => $message,
			'headers' => '',
		);

		/**
		 * Filters the email that delivers a requested magic link.
		 *
		 * @param array   $email Keys: to, subject, message, headers.
		 * @param WP_User $user  Recipient.
		 * @param string  $url   Magic link.
		 */
		$email = apply_filters( 'gselml_request_email', $email, $user, $url );

		wp_mail( $email['to'], $email['subject'], $email['message'], $email['headers'] );
	}

	/**
	 * Redirects back to the form page with a result code and stops.
	 *
	 * @param string $url    Form page.
	 * @param string $result Result code.
	 */
	private function back( $url, $result ) {
		wp_safe_redirect( add_query_arg( self::RESULT_VAR, $result, $url ) . '#gselml-request' );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Registration checkbox
	 * ------------------------------------------------------------------- */

	/**
	 * Whether the registration checkbox is offered.
	 *
	 * @return bool
	 */
	private function registration_enabled() {
		$settings = $this->core->get_settings();
		if ( empty( $settings['register_checkbox'] ) ) {
			return false;
		}
		// New users get the default role: do not offer links if that role is blocked.
		return ! in_array( get_option( 'default_role' ), (array) $settings['blocked_roles'], true );
	}

	/**
	 * Adds the checkbox to wp-login.php?action=register.
	 */
	public function render_register_checkbox() {
		if ( ! $this->registration_enabled() ) {
			return;
		}
		wp_nonce_field( 'glocalsaino_magic_link_register', '_gselml_register_nonce' );
		?>
		<p class="gselml-register">
			<label for="gselml_register_link">
				<input type="checkbox" name="gselml_register_link" id="gselml_register_link" value="1" />
				<?php esc_html_e( 'Also email me a magic link to log in without a password', 'glocalsaino-easy-login-by-magic-link' ); ?>
			</label>
		</p>
		<br />
		<?php
	}

	/**
	 * Creates the link for a new user who ticked the checkbox.
	 *
	 * @param int $user_id New user ID.
	 */
	public function handle_registration( $user_id ) {
		if ( ! $this->registration_enabled() || empty( $_POST['gselml_register_link'] ) ) {
			return;
		}
		$nonce = isset( $_POST['_gselml_register_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_gselml_register_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'glocalsaino_magic_link_register' ) ) {
			return;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof WP_User || ! $this->core->user_can_receive_emailed_links( $user ) ) {
			return;
		}

		$settings    = $this->core->get_settings();
		$same_device = ! empty( $settings['request_same_device'] );
		$expires_in  = (int) $settings['register_expiry'];

		$token = $this->core->create_link(
			$user_id,
			array(
				'label'       => __( 'Registration', 'glocalsaino-easy-login-by-magic-link' ),
				'expires_in'  => $expires_in,
				'max_uses'    => 1,
				'confirm'     => ! empty( $settings['request_confirm'] ),
				'source'      => 'register',
				'device_mode' => $same_device ? 'requester' : 'any',
				'device_hash' => $same_device ? $this->core->device_hash( $this->core->ensure_device_id() ) : '',
			)
		);

		$this->registration_links[ $user_id ] = array(
			'url'         => $this->core->get_link_url( $token ),
			'expires_in'  => $expires_in,
			'same_device' => $same_device,
		);
	}

	/**
	 * Appends the magic link to the "new user" email WordPress sends.
	 *
	 * @param array   $email Email parts (to, subject, message, headers).
	 * @param WP_User $user  New user.
	 * @return array
	 */
	public function add_link_to_new_user_email( $email, $user ) {
		if ( ! $user instanceof WP_User || ! isset( $this->registration_links[ $user->ID ] ) ) {
			return $email;
		}

		$link = $this->registration_links[ $user->ID ];

		$text  = "\r\n" . __( 'Or log in right away, without a password, with this link:', 'glocalsaino-easy-login-by-magic-link' ) . "\r\n\r\n";
		$text .= $link['url'] . "\r\n\r\n";
		/* translators: %s: time span such as "15 minutes" or "1 day". */
		$text .= sprintf( __( 'The link works once and expires in %s.', 'glocalsaino-easy-login-by-magic-link' ), GSELML_Core::expiry_label( $link['expires_in'] ) ) . "\r\n";
		if ( $link['same_device'] ) {
			$text .= __( 'Open it in the same browser where you registered.', 'glocalsaino-easy-login-by-magic-link' ) . "\r\n";
		}

		$email['message'] .= $text;

		return $email;
	}
}
