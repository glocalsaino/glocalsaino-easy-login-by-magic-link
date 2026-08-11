<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPLL_Admin {

	public function register_menu(): void {
		add_options_page(
			__( 'WP Login Links', 'wpll' ),
			__( 'WP Login Links', 'wpll' ),
			'manage_options',
			'wpll-settings',
			[ $this, 'render_page' ]
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'settings_page_wpll-settings' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'wpll-admin',
			WPLL_PLUGIN_URL . 'assets/css/admin.css',
			[],
			WPLL_VERSION
		);
		wp_enqueue_script(
			'wpll-admin',
			WPLL_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			WPLL_VERSION,
			true
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$core  = new WPLL_Core();
		$users = $core->get_users();
		$links = $core->get_links();
		?>
		<div class="wrap wpll-wrap">
			<h1><?php esc_html_e( 'WP Login Links', 'wpll' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Genera enlaces firmados que autentican a un usuario de WordPress automáticamente, sin contraseña. El token es un valor aleatorio de 256 bits que nunca contiene ni expone las credenciales del usuario.', 'wpll' ); ?></p>

			<?php if ( isset( $_GET['wpll_created'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Login link generado correctamente.', 'wpll' ); ?></p></div>
			<?php elseif ( isset( $_GET['wpll_error'] ) ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'El usuario seleccionado no existe.', 'wpll' ); ?></p></div>
			<?php endif; ?>

			<div class="wpll-card">
				<h2><?php esc_html_e( 'Crear enlace', 'wpll' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="wpll_create" />
					<?php wp_nonce_field( 'wpll_create' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="wpll_user"><?php esc_html_e( 'Usuario', 'wpll' ); ?></label></th>
							<td>
								<select id="wpll_user" name="user_id">
									<?php foreach ( $users as $u ) : ?>
										<option value="<?php echo esc_attr( $u->ID ); ?>">
											<?php echo esc_html( $u->display_name . ' (' . $u->user_email . ')' ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<?php if ( empty( $users ) ) : ?>
									<p class="description"><?php esc_html_e( 'No hay usuarios registrados.', 'wpll' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wpll_label"><?php esc_html_e( 'Etiqueta (opcional)', 'wpll' ); ?></label></th>
							<td>
								<input type="text" id="wpll_label" name="label" class="regular-text"
									placeholder="<?php esc_attr_e( 'Ej. Acceso soporte mayo', 'wpll' ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wpll_redirect"><?php esc_html_e( 'Redirigir a (opcional)', 'wpll' ); ?></label></th>
							<td>
								<input type="url" id="wpll_redirect" name="redirect_to" class="regular-text"
									placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>" />
								<p class="description"><?php esc_html_e( 'URL a la que se redirige al usuario tras el login. Por defecto: portada del sitio.', 'wpll' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wpll_expires"><?php esc_html_e( 'Caducidad', 'wpll' ); ?></label></th>
							<td>
								<select id="wpll_expires" name="expires_in">
									<option value="3600"><?php esc_html_e( '1 hora', 'wpll' ); ?></option>
									<option value="86400" selected><?php esc_html_e( '1 día', 'wpll' ); ?></option>
									<option value="604800"><?php esc_html_e( '7 días', 'wpll' ); ?></option>
									<option value="2592000"><?php esc_html_e( '30 días', 'wpll' ); ?></option>
									<option value="0"><?php esc_html_e( 'Nunca', 'wpll' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wpll_uses"><?php esc_html_e( 'Usos máximos', 'wpll' ); ?></label></th>
							<td>
								<select id="wpll_uses" name="max_uses">
									<option value="1" selected><?php esc_html_e( '1 uso', 'wpll' ); ?></option>
									<option value="5"><?php esc_html_e( '5 usos', 'wpll' ); ?></option>
									<option value="10"><?php esc_html_e( '10 usos', 'wpll' ); ?></option>
									<option value="0"><?php esc_html_e( 'Ilimitado', 'wpll' ); ?></option>
								</select>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Generar login link', 'wpll' ) ); ?>
				</form>
			</div>

			<?php if ( ! empty( $links ) ) : ?>
				<div class="wpll-card">
					<h2><?php esc_html_e( 'Enlaces existentes', 'wpll' ); ?></h2>
					<table class="widefat striped wpll-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Usuario', 'wpll' ); ?></th>
								<th><?php esc_html_e( 'Etiqueta', 'wpll' ); ?></th>
								<th><?php esc_html_e( 'Enlace', 'wpll' ); ?></th>
								<th><?php esc_html_e( 'Caduca', 'wpll' ); ?></th>
								<th><?php esc_html_e( 'Usos', 'wpll' ); ?></th>
								<th><?php esc_html_e( 'Estado', 'wpll' ); ?></th>
								<th><?php esc_html_e( 'Acciones', 'wpll' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $links as $token => $link ) :
								$u          = get_user_by( 'id', $link['user_id'] );
								$user_label = $u ? $u->display_name . ' (' . $u->user_email . ')' : '#' . $link['user_id'];
								$link_url   = $core->get_link_url( $token );
								$expired    = $core->is_expired( $link );
								$exhausted  = $core->is_exhausted( $link );
								$uses_label = $link['uses'] . ( $link['max_uses'] ? ' / ' . $link['max_uses'] : '' );
								?>
								<tr>
									<td><?php echo esc_html( $user_label ); ?></td>
									<td><?php echo esc_html( $link['label'] ?: '—' ); ?></td>
									<td>
										<input type="text" readonly class="wpll-url-field" value="<?php echo esc_url( $link_url ); ?>" onclick="this.select();" />
										<button type="button" class="button wpll-copy-link" data-url="<?php echo esc_url( $link_url ); ?>"><?php esc_html_e( 'Copiar', 'wpll' ); ?></button>
									</td>
									<td><?php echo $link['expires'] ? esc_html( date_i18n( 'd/m/Y H:i', $link['expires'] ) ) : esc_html__( 'Nunca', 'wpll' ); ?></td>
									<td><?php echo esc_html( $uses_label ); ?></td>
									<td>
										<?php if ( $expired ) : ?>
											<span class="wpll-status wpll-status--expired"><?php esc_html_e( 'Caducado', 'wpll' ); ?></span>
										<?php elseif ( $exhausted ) : ?>
											<span class="wpll-status wpll-status--exhausted"><?php esc_html_e( 'Agotado', 'wpll' ); ?></span>
										<?php else : ?>
											<span class="wpll-status wpll-status--active"><?php esc_html_e( 'Activo', 'wpll' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
											<input type="hidden" name="action" value="wpll_revoke" />
											<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>" />
											<?php wp_nonce_field( 'wpll_revoke' ); ?>
											<button type="submit" class="button-link-delete"
												onclick="return confirm('<?php echo esc_js( __( '¿Revocar este enlace? Dejará de funcionar inmediatamente.', 'wpll' ) ); ?>');">
												<?php esc_html_e( 'Revocar', 'wpll' ); ?>
											</button>
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
}
