<?php
/**
 * Settings screen: Easy Login → Settings.
 *
 * @package GlocalSaino_Easy_Login_By_Magic_Link
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings for emailed links, registration and redirects by role.
 */
class GSELML_Settings {

	/**
	 * Settings page slug.
	 */
	const PAGE_SLUG = 'glocalsaino-easy-login-by-magic-link-settings';

	/**
	 * Settings group used by options.php.
	 */
	const GROUP = 'glocalsaino_magic_link_settings_group';

	/**
	 * Expiry choices for links sent by email, in seconds.
	 */
	const EMAIL_EXPIRY_CHOICES = array( 900, 3600, 86400, 604800 );

	/**
	 * Core instance.
	 *
	 * @var GSELML_Core
	 */
	private $core;

	/**
	 * Hook suffix of the settings page.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Constructor.
	 *
	 * @param GSELML_Core $core Core instance.
	 */
	public function __construct( GSELML_Core $core ) {
		$this->core = $core;
	}

	/**
	 * Hook suffix of the settings page ('' before admin_menu).
	 *
	 * @return string
	 */
	public function hook() {
		return $this->hook;
	}

	/**
	 * Registers the option and its sanitizer.
	 */
	public function register_setting() {
		register_setting(
			self::GROUP,
			GSELML_Core::SETTINGS_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => GSELML_Core::default_settings(),
			)
		);
	}

	/**
	 * Adds the submenu under "Easy Login".
	 */
	public function register_menu() {
		$this->hook = (string) add_submenu_page(
			GSELML_Core::PAGE_SLUG,
			__( 'Easy Login settings', 'glocalsaino-easy-login-by-magic-link' ),
			__( 'Settings', 'glocalsaino-easy-login-by-magic-link' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * URL of the settings page.
	 *
	 * @return string
	 */
	public static function page_url() {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * Sanitizes the submitted settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = GSELML_Core::default_settings();
		$roles    = array_keys( wp_roles()->get_names() );
		$clean    = array();

		$expiry                  = isset( $input['request_expiry'] ) ? absint( $input['request_expiry'] ) : $defaults['request_expiry'];
		$clean['request_expiry'] = in_array( $expiry, self::EMAIL_EXPIRY_CHOICES, true ) ? $expiry : $defaults['request_expiry'];

		$expiry                   = isset( $input['register_expiry'] ) ? absint( $input['register_expiry'] ) : $defaults['register_expiry'];
		$clean['register_expiry'] = in_array( $expiry, self::EMAIL_EXPIRY_CHOICES, true ) ? $expiry : $defaults['register_expiry'];

		$clean['request_confirm']     = empty( $input['request_confirm'] ) ? 0 : 1;
		$clean['request_same_device'] = empty( $input['request_same_device'] ) ? 0 : 1;
		$clean['register_checkbox']   = empty( $input['register_checkbox'] ) ? 0 : 1;
		$clean['limit_email']         = isset( $input['limit_email'] ) ? min( 50, max( 1, absint( $input['limit_email'] ) ) ) : $defaults['limit_email'];
		$clean['limit_ip']            = isset( $input['limit_ip'] ) ? min( 500, max( 1, absint( $input['limit_ip'] ) ) ) : $defaults['limit_ip'];

		$blocked                = isset( $input['blocked_roles'] ) ? array_map( 'sanitize_key', (array) $input['blocked_roles'] ) : array();
		$clean['blocked_roles'] = array_values( array_intersect( $blocked, $roles ) );

		$clean['role_redirects'] = array();
		$raw_redirects           = isset( $input['role_redirects'] ) ? (array) $input['role_redirects'] : array();
		foreach ( $roles as $role ) {
			$url = isset( $raw_redirects[ $role ] ) ? esc_url_raw( trim( (string) $raw_redirects[ $role ] ) ) : '';
			if ( '' === $url ) {
				continue;
			}
			if ( wp_validate_redirect( $url, '' ) !== $url ) {
				add_settings_error(
					GSELML_Core::SETTINGS_KEY,
					'bad_redirect_' . $role,
					/* translators: %s: role name. */
					sprintf( __( 'The redirect for the role "%s" was ignored: it must point to this site.', 'glocalsaino-easy-login-by-magic-link' ), translate_user_role( wp_roles()->get_names()[ $role ] ) )
				);
				continue;
			}
			$clean['role_redirects'][ $role ] = $url;
		}

		return $clean;
	}

	/**
	 * Renders the settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->core->get_settings();
		$key      = GSELML_Core::SETTINGS_KEY;
		$roles    = wp_roles()->get_names();
		?>
		<div class="wrap gselml-wrap">
			<h1><?php esc_html_e( 'Easy Login settings', 'glocalsaino-easy-login-by-magic-link' ); ?></h1>
			<?php settings_errors(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php settings_fields( self::GROUP ); ?>

				<div class="gselml-card">
					<h2><?php esc_html_e( 'Login link request form', 'glocalsaino-easy-login-by-magic-link' ); ?></h2>
					<p>
						<?php esc_html_e( 'Add this shortcode to any page to show a form where users enter their email address and receive a login link:', 'glocalsaino-easy-login-by-magic-link' ); ?>
						<code>[<?php echo esc_html( GSELML_Requests::SHORTCODE ); ?>]</code>
					</p>
					<p class="description">
						<?php
						printf(
							/* translators: %s: example shortcode with the redirect attribute. */
							esc_html__( 'Optional: choose the landing page with %s. The answer is always the same, whether the address belongs to an account or not, so the form does not reveal who is registered.', 'glocalsaino-easy-login-by-magic-link' ),
							'<code>[' . esc_html( GSELML_Requests::SHORTCODE ) . ' redirect="' . esc_url( home_url( '/my-account/' ) ) . '"]</code>'
						);
						?>
					</p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="gselml_request_expiry"><?php esc_html_e( 'Link expires after', 'glocalsaino-easy-login-by-magic-link' ); ?></label></th>
							<td>
								<?php $this->expiry_select( 'gselml_request_expiry', $key . '[request_expiry]', (int) $settings['request_expiry'] ); ?>
								<p class="description"><?php esc_html_e( 'Links sent by email always work only once.', 'glocalsaino-easy-login-by-magic-link' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Rate limits', 'glocalsaino-easy-login-by-magic-link' ); ?></th>
							<td>
								<label for="gselml_limit_email">
									<?php
									printf(
										/* translators: %s: number input. */
										esc_html__( 'At most %s requests per email address per hour', 'glocalsaino-easy-login-by-magic-link' ),
										'<input type="number" min="1" max="50" class="small-text" id="gselml_limit_email" name="' . esc_attr( $key ) . '[limit_email]" value="' . esc_attr( (string) $settings['limit_email'] ) . '" />'
									);
									?>
								</label><br />
								<label for="gselml_limit_ip">
									<?php
									printf(
										/* translators: %s: number input. */
										esc_html__( 'At most %s requests per IP address per hour', 'glocalsaino-easy-login-by-magic-link' ),
										'<input type="number" min="1" max="500" class="small-text" id="gselml_limit_ip" name="' . esc_attr( $key ) . '[limit_ip]" value="' . esc_attr( (string) $settings['limit_ip'] ) . '" />'
									);
									?>
								</label>
							</td>
						</tr>
					</table>
				</div>

				<div class="gselml-card">
					<h2><?php esc_html_e( 'Registration form', 'glocalsaino-easy-login-by-magic-link' ); ?></h2>
					<?php if ( ! get_option( 'users_can_register' ) ) : ?>
						<div class="notice notice-info inline"><p>
							<?php
							printf(
								/* translators: %s: link to Settings → General. */
								esc_html__( 'User registration is currently turned off in %s, so the checkbox will not be visible.', 'glocalsaino-easy-login-by-magic-link' ),
								'<a href="' . esc_url( admin_url( 'options-general.php' ) ) . '">' . esc_html__( 'Settings → General', 'glocalsaino-easy-login-by-magic-link' ) . '</a>'
							);
							?>
						</p></div>
					<?php endif; ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Checkbox', 'glocalsaino-easy-login-by-magic-link' ); ?></th>
							<td>
								<label for="gselml_register_checkbox">
									<input type="checkbox" id="gselml_register_checkbox" name="<?php echo esc_attr( $key ); ?>[register_checkbox]" value="1" <?php checked( ! empty( $settings['register_checkbox'] ) ); ?> />
									<?php esc_html_e( 'Add a checkbox to the WordPress registration form so new users can get a magic link in their welcome email', 'glocalsaino-easy-login-by-magic-link' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="gselml_register_expiry"><?php esc_html_e( 'Link expires after', 'glocalsaino-easy-login-by-magic-link' ); ?></label></th>
							<td><?php $this->expiry_select( 'gselml_register_expiry', $key . '[register_expiry]', (int) $settings['register_expiry'] ); ?></td>
						</tr>
					</table>
				</div>

				<div class="gselml-card">
					<h2><?php esc_html_e( 'Security of emailed links', 'glocalsaino-easy-login-by-magic-link' ); ?></h2>
					<p class="description"><?php esc_html_e( 'These options apply to links sent by the request form and by the registration checkbox.', 'glocalsaino-easy-login-by-magic-link' ); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Login button page', 'glocalsaino-easy-login-by-magic-link' ); ?></th>
							<td>
								<label for="gselml_request_confirm">
									<input type="checkbox" id="gselml_request_confirm" name="<?php echo esc_attr( $key ); ?>[request_confirm]" value="1" <?php checked( ! empty( $settings['request_confirm'] ) ); ?> />
									<?php esc_html_e( 'Show a page with a "Log in" button so email scanners cannot use up the link (recommended)', 'glocalsaino-easy-login-by-magic-link' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Same-browser protection', 'glocalsaino-easy-login-by-magic-link' ); ?></th>
							<td>
								<label for="gselml_request_same_device">
									<input type="checkbox" id="gselml_request_same_device" name="<?php echo esc_attr( $key ); ?>[request_same_device]" value="1" <?php checked( ! empty( $settings['request_same_device'] ) ); ?> />
									<?php esc_html_e( 'The link only works in the browser where it was requested', 'glocalsaino-easy-login-by-magic-link' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'A copied, forwarded or intercepted link is useless on any other device. Users must open the email on the same device and in the same browser; links opened inside an email app\'s own browser may not work.', 'glocalsaino-easy-login-by-magic-link' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Roles that cannot get links by email', 'glocalsaino-easy-login-by-magic-link' ); ?></th>
							<td>
								<fieldset>
									<?php foreach ( $roles as $role => $name ) : ?>
										<label>
											<input type="checkbox" name="<?php echo esc_attr( $key ); ?>[blocked_roles][]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, (array) $settings['blocked_roles'], true ) ); ?> />
											<?php echo esc_html( translate_user_role( $name ) ); ?>
										</label><br />
									<?php endforeach; ?>
								</fieldset>
								<p class="description"><?php esc_html_e( 'Users with these roles get no email from the request form (the form still shows the usual message). Administrators can still create links for them manually.', 'glocalsaino-easy-login-by-magic-link' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div class="gselml-card">
					<h2><?php esc_html_e( 'Redirect after login, by role', 'glocalsaino-easy-login-by-magic-link' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Page each role lands on after logging in with a magic link. A page set on the link itself (or in the shortcode) takes priority. Empty: the home page.', 'glocalsaino-easy-login-by-magic-link' ); ?></p>
					<table class="form-table" role="presentation">
						<?php foreach ( $roles as $role => $name ) : ?>
							<tr>
								<th scope="row"><label for="gselml_redirect_<?php echo esc_attr( $role ); ?>"><?php echo esc_html( translate_user_role( $name ) ); ?></label></th>
								<td>
									<input type="url" class="regular-text" id="gselml_redirect_<?php echo esc_attr( $role ); ?>" name="<?php echo esc_attr( $key ); ?>[role_redirects][<?php echo esc_attr( $role ); ?>]" value="<?php echo esc_url( isset( $settings['role_redirects'][ $role ] ) ? $settings['role_redirects'][ $role ] : '' ); ?>" placeholder="<?php echo esc_attr( 'administrator' === $role ? admin_url() : home_url( '/' ) ); ?>" />
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
				</div>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Prints an expiry dropdown for emailed links.
	 *
	 * @param string $id       Field ID.
	 * @param string $name     Field name.
	 * @param int    $selected Selected value.
	 */
	private function expiry_select( $id, $name, $selected ) {
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
		foreach ( self::EMAIL_EXPIRY_CHOICES as $seconds ) {
			echo '<option value="' . esc_attr( (string) $seconds ) . '" ' . selected( $selected, $seconds, false ) . '>' . esc_html( GSELML_Core::expiry_label( $seconds ) ) . '</option>';
		}
		echo '</select>';
	}
}
