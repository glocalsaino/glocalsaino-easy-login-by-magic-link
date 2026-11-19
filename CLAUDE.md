# GlocalSaino Easy Login by Magic Link

Enlaces mágicos de inicio de sesión para WordPress. Versión 1.0.0, lista para enviar a WordPress.org (no enviada a 2026-11-19). **Repo público.**

- Prefijos: clases/constantes `GSELML_`; globales `glocalsaino_magic_link_*` (opción `glocalsaino_magic_link_links` sin autoload, query arg `glocalsaino_magic_link`, admin_post `glocalsaino_magic_link_create/_revoke`); hook para desarrolladores `gselml_logged_in` (Plugin Check exige ese prefijo).
- Menú de nivel superior "Easy Login" (dashicons-admin-network, posición 71).
- A prueba de escáneres de correo: el GET muestra una página con botón (noindex, Referrer-Policy no-referrer); solo el POST con nonce `glocalsaino_magic_link_login_<token>` inicia sesión y cuenta un uso. Flag `confirm` por enlace, activo por defecto.
- Sin add-ons todavía, así que no necesita página Extensions.
- es_ES en `translations/` (solo repo). Zip: `git -C ~/proyectos/wordpress/glocalsaino-easy-login-by-magic-link archive --format=zip --prefix=glocalsaino-easy-login-by-magic-link/ -o ~/Desktop/glocalsaino-easy-login-by-magic-link-<versión>.zip HEAD` (ver ~/.claude/CLAUDE.md).
