<?php
/**
 * Admin screen: top-level "Easy Login" menu.
 *
 * @package GlocalSaino_Easy_Login_By_Magic_Link
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page for creating, listing and revoking magic links.
 */
class GSELML_Admin {

	/**
	 * Core instance.
	 *
	 * @var GSELML_Core
	 */
	private $core;

	/**
	 * Hook suffix returned by add_menu_page().
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
	 * Settings screen, for loading the shared assets there too.
	 *
	 * @var GSELML_Settings|null
	 */
	private $settings;

	/**
	 * Sets the settings screen.
	 *
	 * @param GSELML_Settings $settings Settings screen.
	 */
	public function set_settings( GSELML_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Registers the top-level admin menu.
	 */
	public function register_menu() {
		$this->hook = (string) add_menu_page(
			__( 'Easy Login by Magic Link', 'glocalsaino-easy-login-by-magic-link' ),
			__( 'Easy Login', 'glocalsaino-easy-login-by-magic-link' ),
			'manage_options',
			GSELML_Core::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-admin-network',
			71
		);
		// Same slug as the parent: renames the first submenu entry.
		add_submenu_page(
			GSELML_Core::PAGE_SLUG,
			__( 'Easy Login by Magic Link', 'glocalsaino-easy-login-by-magic-link' ),
			__( 'Magic links', 'glocalsaino-easy-login-by-magic-link' ),
			'manage_options',
			GSELML_Core::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Adds a "Settings" link in the Plugins list.
	 *
	 * @param array $links Action links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( $this->core->admin_page_url() ) . '">' . esc_html__( 'Settings', 'glocalsaino-easy-login-by-magic-link' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Loads CSS/JS on the plugin's own page only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_assets( $hook ) {
		$hooks = array_filter( array( $this->hook, $this->settings ? $this->settings->hook() : '' ) );
		if ( ! in_array( $hook, $hooks, true ) ) {
			return;
		}
		wp_enqueue_style(
			'glocalsaino-magic-link-admin',
			GSELML_URL . 'assets/css/admin.css',
			array(),
			GSELML_VERSION
		);
		wp_enqueue_script(
			'glocalsaino-magic-link-admin',
			GSELML_URL . 'assets/js/admin.js',
			array(),
			GSELML_VERSION,
			true
		);
		wp_localize_script(
			'glocalsaino-magic-link-admin',
			'glocalsainoMagicLinkAdmin',
			array(
				'copied'        => __( 'Copied', 'glocalsaino-easy-login-by-magic-link' ),
				'confirmRevoke' => __( 'Revoke this link? It will stop working immediately.', 'glocalsaino-easy-login-by-magic-link' ),
			)
		);
	}

	/**
	 * Renders the admin page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$users = $this->core->get_users();
		$links = $this->core->get_links();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only selects which notice to display after a nonce-checked action.
		$notice = isset( $_GET['gselml_notice'] ) ? sanitize_key( wp_unslash( $_GET['gselml_notice'] ) ) : '';

		$notices = array(
			'created'      => array( 'success', __( 'Magic link created. Copy it from the list below.', 'glocalsaino-easy-login-by-magic-link' ) ),
			'revoked'      => array( 'success', __( 'Magic link revoked.', 'glocalsaino-easy-login-by-magic-link' ) ),
			'no_user'      => array( 'error', __( 'The selected user does not exist.', 'glocalsaino-easy-login-by-magic-link' ) ),
			'bad_redirect' => array( 'error', __( 'The redirect URL must point to this site.', 'glocalsaino-easy-login-by-magic-link' ) ),
			'bad_ip'       => array( 'error', __( 'Enter at least one valid IP address or range (for example 203.0.113.7 or 203.0.113.0/24).', 'glocalsaino-easy-login-by-magic-link' ) ),
		);

		$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		?>
		<div class="wrap gselml-wrap">
			<h1><?php esc_html_e( 'Easy Login by Magic Link', 'glocalsaino-easy-login-by-magic-link' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Create links that log a WordPress user in with one click, without a password. Each link holds a random 256-bit token; it never contains or reveals the user\'s credentials.', 'glocalsaino-easy-login-by-magic-link' ); ?></p>

			<?php if ( isset( $notices[ $notice ] ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notices[ $notice ][0] ); ?> is-dismissible"><p><?php echo esc_html( $notices[ $notice ][1] ); ?></p></div>
			<?php endif; ?>

			<div class="gselml-card">
				<h2><?php esc_html_e( 'Create a link', 'glocalsaino-easy-login-by-magic-link' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="glocalsaino_magic_link_create" />
					<?php wp_nonce_field( 'glocalsaino_magic_link_create' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="gselml_user"><?php esc_html_e( 'User', 'glocalsaino-easy-login-by-magic-link' ); ?></label></th>
							<td>
								<select id="gselml_user" name="user_id">
									<?php foreach ( $users as $gselml_user ) : ?>
										<option value="<?php echo esc_attr( $gselml_user->ID ); ?>"><?php echo esc_html( $gselml_user->display_name . ' (' . $gselml_user->user_email . ')' ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'A link for an administrator gives full access to the dashboard: share it with care.', 'glocalsaino-easy-login-by-magic-link' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="gselml_label"><?php esc_html_e( 'Label (optional)', 'glocalsaino-easy-login-by-magic-link' ); ?></label></th>
							<td>
								<input type="text" id="gselml_label" name="label" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Support access, May', 'glocalsaino-easy-login-by-magic-link' ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="gselml_redirect"><?php esc_html_e( 'Redirect to (optional)', 'glocalsaino-easy-login-by-magic-link' ); ?></label></th>
							<td>
								<input type="url" id="gselml_redirect" name="redirect_to" class="regular-text" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>" />
								<p class="description"><?php esc_html_e( 'Page of this site the user lands on after logging in. Default: the home page.', 'glocalsaino-easy-login-by-magic-link' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="gselml_expires"><?php esc_html_e( 'Expires after', 'glocalsaino-easy-login-by-magic-link' ); ?></label></th>
							<td>
								<select id="gselml_expires" name="expires_in">
									<option value="900"><?php esc_html_e( '15 minutes', 'glocalsaino-easy-login-by-magic-link' ); ?></option>
									<option value="3600"><?php esc_html_e( '1 hour', 'glocalsaino-easy-login-by-magic-link' ); ?></option>
									<option value="86400" selected><?php esc_html_e( '1 day', 'glocalsaino-easy-login-by-magic-link' ); ?></option>
									<option value="604800"><?php esc_html_e( '7 days', 'glocalsaino-easy-login-by-magic-link' ); ?></option>
									<option value="2592000"><?php esc_html_e( '30 days', 'glocalsaino-easy-login-by-magic-link' ); ?></option>
									<option value="0"><?php esc_html_e( 'Never', 'glocalsaino-easy-login-by-magic-link' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="gselml_uses"><?php esc_html_e( 'Maximum uses', 'glocalsaino-easy-login-by-magic-link' ); ?></label></th>
							<td>
								<select id="gselml_uses" name="max_uses">
									<option value="1" selected><?php esc_html_e( '1 use', 'glocalsaino-easy-login-by-magic-link' ); ?></option>
									<option value="5"><?php esc_html_e( '5 uses', 'glocalsaino-easy-login-by-magic-link' ); ?></option>
									<option value="10"><?php esc_html_e( '10 uses', 'glocalsaino-easy-login-by-magic-link' ); ?></option>
									<option value="0"><?php esc_html_e( 'Unlimited', 'glocalsaino-easy-login-by-magic-link' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Login button page', 'glocalsaino-easy-login-by-magic-link' ); ?></th>
							<td>
								<label for="gselml_confirm">
									<input type="checkbox" id="gselml_confirm" name="confirm" value="1" checked />
									<?php esc_html_e( 'Show a page with a "Log in" button before logging the user in (recommended)', 'glocalsaino-easy-login-by-magic-link' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Email security filters often open links automatically to scan them. With this page, only a real click on the button logs in and counts as a use. Untick it for a direct one-click login.', 'glocalsaino-easy-login-by-magic-link' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="gselml_ip_mode"><?php esc_html_e( 'IP restriction', 'glocalsaino-easy-login-by-magic-link' ); ?></label></th>
							<td>
								<select id="gselml_ip_mode" name="ip_mode">
									<option value="any" selected><?php esc_html_e( 'No restriction (any IP address)', 'glocalsaino-easy-login-by-magic-link' ); ?></option>
									<option value="first"><?php esc_html_e( 'Lock to the IP address of the first login', 'glocalsaino-easy-login-by-magic-link' ); ?></option>
									<option value="list"><?php esc_html_e( 'Only these IP addresses…', 'glocalsaino-easy-login-by-magic-link' ); ?></option>
								</select>
								<p class="gselml-ip-list">
									<label for="gselml_ips" class="screen-reader-text"><?php esc_html_e( 'Allowed IP addresses', 'glocalsaino-easy-login-by-magic-link' ); ?></label>
									<textarea id="gselml_ips" name="ips" rows="3" class="regular-text code" placeholder="203.0.113.7&#10;198.51.100.0/24"></textarea>
								</p>
								<p class="description">
									<?php
									printf(
										/* translators: %s: the administrator's current IP address. */
										esc_html__( 'One IP address or CIDR range per line. Your current IP address is %s.', 'glocalsaino-easy-login-by-magic-link' ),
										'<code>' . esc_html( $this->core->client_ip() ) . '</code>'
									);
									?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="gselml_device_mode"><?php esc_html_e( 'Device restriction', 'glocalsaino-easy-login-by-magic-link' ); ?></label></th>
							<td>
								<select id="gselml_device_mode" name="device_mode">
									<option value="any" selected><?php esc_html_e( 'No restriction (any device)', 'glocalsaino-easy-login-by-magic-link' ); ?></option>
									<option value="first"><?php esc_html_e( 'Lock to the browser of the first login', 'glocalsaino-easy-login-by-magic-link' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'With the lock, the first login stores a random identifier in that browser and the link keeps working only there. Useful for links with several uses: if the link leaks, it is useless on any other device.', 'glocalsaino-easy-login-by-magic-link' ); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Create magic link', 'glocalsaino-easy-login-by-magic-link' ) ); ?>
				</form>
			</div>

			<?php if ( ! empty( $links ) ) : ?>
				<div class="gselml-card">
					<h2><?php esc_html_e( 'Existing links', 'glocalsaino-easy-login-by-magic-link' ); ?></h2>
					<table class="widefat striped gselml-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'User', 'glocalsaino-easy-login-by-magic-link' ); ?></th>
								<th><?php esc_html_e( 'Label', 'glocalsaino-easy-login-by-magic-link' ); ?></th>
								<th><?php esc_html_e( 'Link', 'glocalsaino-easy-login-by-magic-link' ); ?></th>
								<th><?php esc_html_e( 'Expires', 'glocalsaino-easy-login-by-magic-link' ); ?></th>
								<th><?php esc_html_e( 'Uses', 'glocalsaino-easy-login-by-magic-link' ); ?></th>
								<th><?php esc_html_e( 'Login', 'glocalsaino-easy-login-by-magic-link' ); ?></th>
								<th><?php esc_html_e( 'Restrictions', 'glocalsaino-easy-login-by-magic-link' ); ?></th>
								<th><?php esc_html_e( 'Status', 'glocalsaino-easy-login-by-magic-link' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'glocalsaino-easy-login-by-magic-link' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $links as $gselml_token => $gselml_link ) :
								$gselml_link  = $this->core->normalize_link( $gselml_link );
								$gselml_user  = get_user_by( 'id', (int) $gselml_link['user_id'] );
								$gselml_who   = $gselml_user ? $gselml_user->display_name . ' (' . $gselml_user->user_email . ')' : '#' . (int) $gselml_link['user_id'];
								$gselml_url   = $this->core->get_link_url( (string) $gselml_token );
								$gselml_uses  = (int) $gselml_link['uses'] . ( ! empty( $gselml_link['max_uses'] ) ? ' / ' . (int) $gselml_link['max_uses'] : '' );
								$gselml_label = '' !== (string) $gselml_link['label'] ? (string) $gselml_link['label'] : '—';
								?>
								<tr>
									<td><?php echo esc_html( $gselml_who ); ?></td>
									<td><?php echo esc_html( $gselml_label ); ?></td>
									<td>
										<input type="text" readonly class="gselml-url-field" value="<?php echo esc_url( $gselml_url ); ?>" />
										<button type="button" class="button gselml-copy-link" data-url="<?php echo esc_url( $gselml_url ); ?>"><?php esc_html_e( 'Copy', 'glocalsaino-easy-login-by-magic-link' ); ?></button>
									</td>
									<td><?php echo ! empty( $gselml_link['expires'] ) ? esc_html( wp_date( $date_format, (int) $gselml_link['expires'] ) ) : esc_html__( 'Never', 'glocalsaino-easy-login-by-magic-link' ); ?></td>
									<td><?php echo esc_html( $gselml_uses ); ?></td>
									<td><?php echo $this->core->needs_confirmation( $gselml_link ) ? esc_html__( 'Button page', 'glocalsaino-easy-login-by-magic-link' ) : esc_html__( 'Direct', 'glocalsaino-easy-login-by-magic-link' ); ?></td>
									<td><?php echo esc_html( implode( ' · ', $this->describe_restrictions( $gselml_link ) ) ); ?></td>
									<td>
										<?php if ( $this->core->is_expired( $gselml_link ) ) : ?>
											<span class="gselml-status gselml-status--expired"><?php esc_html_e( 'Expired', 'glocalsaino-easy-login-by-magic-link' ); ?></span>
										<?php elseif ( $this->core->is_exhausted( $gselml_link ) ) : ?>
											<span class="gselml-status gselml-status--exhausted"><?php esc_html_e( 'Used up', 'glocalsaino-easy-login-by-magic-link' ); ?></span>
										<?php else : ?>
											<span class="gselml-status gselml-status--active"><?php esc_html_e( 'Active', 'glocalsaino-easy-login-by-magic-link' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gselml-revoke-form">
											<input type="hidden" name="action" value="glocalsaino_magic_link_revoke" />
											<input type="hidden" name="token" value="<?php echo esc_attr( $gselml_token ); ?>" />
											<?php wp_nonce_field( 'glocalsaino_magic_link_revoke' ); ?>
											<button type="submit" class="button-link button-link-delete"><?php esc_html_e( 'Revoke', 'glocalsaino-easy-login-by-magic-link' ); ?></button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Short descriptions of a link's IP and device restrictions.
	 *
	 * @param array $link Normalized link.
	 * @return string[]
	 */
	private function describe_restrictions( array $link ) {
		$out = array();

		if ( 'list' === $link['ip_mode'] ) {
			/* translators: %s: list of IP addresses. */
			$out[] = sprintf( __( 'IP: %s', 'glocalsaino-easy-login-by-magic-link' ), implode( ', ', (array) $link['ips'] ) );
		} elseif ( 'first' === $link['ip_mode'] ) {
			$out[] = '' !== $link['bound_ip']
				/* translators: %s: IP address. */
				? sprintf( __( 'IP locked to %s', 'glocalsaino-easy-login-by-magic-link' ), $link['bound_ip'] )
				: __( 'IP of first login', 'glocalsaino-easy-login-by-magic-link' );
		}

		if ( 'requester' === $link['device_mode'] ) {
			$out[] = __( 'Requesting browser only', 'glocalsaino-easy-login-by-magic-link' );
		} elseif ( 'first' === $link['device_mode'] ) {
			$out[] = '' !== $link['device_hash']
				? __( 'Locked to one browser', 'glocalsaino-easy-login-by-magic-link' )
				: __( 'Browser of first login', 'glocalsaino-easy-login-by-magic-link' );
		}

		return $out ? $out : array( '—' );
	}
}
