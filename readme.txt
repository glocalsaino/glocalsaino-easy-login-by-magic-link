=== WP Login Links ===
Contributors: rafamm-glocalsaino
Tags: login, magic link, passwordless, one-click login, authentication
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Genera enlaces firmados que autentican a un usuario de WordPress automáticamente, sin contraseña.

== Description ==

**WP Login Links** permite al administrador crear enlaces de un solo clic que inician sesión en WordPress como un usuario concreto sin necesidad de introducir la contraseña.

El enlace contiene un token aleatorio de 256 bits que nunca expone ni transmite las credenciales del usuario. Al hacer clic, el servidor valida el token, establece la cookie de sesión de WordPress y redirige al usuario a la URL configurada.

= Casos de uso =

* **Onboarding de clientes** — envía a un cliente recién registrado un enlace para que acceda sin fricciones por primera vez.
* **Soporte técnico** — da acceso temporal a un agente de soporte sin compartir la contraseña real del usuario.
* **Demos y presentaciones** — acceso directo a un entorno de demostración sin pantalla de login.
* **Recuperación de acceso** — alternativa a "Olvidé mi contraseña" gestionada desde el panel de administración.

= Características =

* **Token de 256 bits** — generado con `random_bytes()`, computacionalmente infalsificable.
* **Caducidad configurable** — 1 hora, 1 día, 7 días, 30 días o sin caducidad.
* **Límite de usos** — 1 uso (por defecto), 5, 10 o ilimitado.
* **URL de destino por enlace** — redirige al usuario a cualquier página del sitio tras el login.
* **Revocación inmediata** — cualquier enlace puede anularse desde el panel de administración.
* **Copia al portapapeles** — botón de copia en un clic para cada enlace generado.
* **Sin dependencias externas** — el plugin no carga ninguna librería ni recurso externo.
* **Sin tablas adicionales** — los tokens se almacenan en la tabla de opciones de WordPress.

= Uso =

1. Ve a **Ajustes → WP Login Links**.
2. Selecciona el usuario, configura la caducidad y el número de usos.
3. Haz clic en **Generar login link** y copia el enlace generado.
4. Envía el enlace al usuario. Al hacer clic quedará autenticado en el sitio.

= Requisitos =

* WordPress 5.0 o superior.
* PHP 7.4 o superior.

== Installation ==

1. Sube la carpeta `wp-login-links` a `/wp-content/plugins/`.
2. Activa el plugin desde **Plugins → Plugins instalados**.
3. Ve a **Ajustes → WP Login Links** para empezar a generar enlaces.

== Frequently Asked Questions ==

= ¿Es seguro? ¿El enlace contiene la contraseña del usuario? =

No. El enlace contiene únicamente un token aleatorio de 256 bits (`bin2hex(random_bytes(32))`). La contraseña real del usuario nunca se incluye ni se transmite. El token es un puntero opaco que el servidor canjea por una sesión al recibir la petición.

= ¿Qué ocurre si el enlace caduca o alcanza el límite de usos? =

El token deja de ser válido y el visitante llega al sitio sin iniciar sesión. El enlace no da ningún mensaje de error propio; el visitante simplemente verá la página de inicio del sitio como usuario no autenticado.

= ¿Puedo generar enlaces para cualquier usuario, incluidos administradores? =

Sí. El selector muestra todos los usuarios del sitio. Ten en cuenta que un enlace de login para un usuario administrador otorga acceso completo al panel de WordPress, por lo que debes gestionar estos enlaces con especial cuidado.

= ¿El plugin funciona con HTTPS y HTTP? =

Sí. La cookie de sesión de WordPress que establece el plugin respeta la configuración del sitio. Si el sitio usa HTTPS, la cookie se marca como `Secure` automáticamente.

= ¿Cuántos enlaces puedo crear? =

No hay límite en el número de enlaces activos. Cada enlace se almacena como una entrada en la opción `wpll_login_links` de la base de datos de WordPress.

== Privacy Policy ==

WP Login Links no recopila, almacena ni transmite datos personales a terceros.

* Los tokens generados se almacenan en la base de datos local de WordPress junto al ID del usuario vinculado, la fecha de creación y el contador de usos.
* No se realizan conexiones externas de ningún tipo.
* Al revocar un enlace, su token se elimina de la base de datos de forma permanente.

== Changelog ==

= 1.0.0 =
* Versión inicial. Generación, listado y revocación de login links con caducidad y límite de usos configurables.

== Upgrade Notice ==

= 1.0.0 =
Versión inicial.
