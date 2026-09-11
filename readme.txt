=== Convoca Publisher ===
Contributors: josecarlosnietoramos
Tags: social-media, publishing, scheduling, telegram, mastodon
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.5.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Auto-publish WordPress posts to social media.

== Description ==

Automatically publish your WordPress posts to social media. No subscriptions, no external dependencies. Tokens are encrypted with AES-256-GCM.

Supported networks: Facebook, Instagram, LinkedIn, Twitter/X, TikTok, Google My Business, Telegram, Mastodon.

* Automatic publishing when a post is published
* Metabox with per-network checkboxes, status, and scheduling
* Customizable message templates per channel
* Variables: {title}, {excerpt}, {url}, {hashtags}, {date}, {author}
* Automatic retry queue (max 2 attempts)
* History of the last 200 posts
* REST API for external integrations
* Tokens encrypted with AES-256-GCM

PRO features (require a license):
* Social media post scheduling
* Scheduled publishing queue and advanced retries
* 8 simultaneous channels

= External Services =

This plugin connects to the APIs of the configured social networks (Facebook, Instagram, LinkedIn, Twitter/X, TikTok, Google My Business, Telegram, Mastodon) to publish content. Credentials are stored encrypted locally. It may also contact getconvoca.app to validate PRO licenses.

== Installation ==

1. Upload the `convoca-publisher` folder to `/wp-content/plugins/`
2. Activate the plugin from the Plugins menu
3. Connect your social networks in Settings > Convoca Publisher

== Changelog ==

= 1.5.0 =
* Una pantalla por canal: estado, credenciales, plantilla, «Verificar conexión» y su guía, todo junto. Se acaban las vueltas por pestañas.
* Estado del canal visible: ✅ Configurado · ❌ Falta token · ⚠️ Error al verificar · 🔑 Necesita reconexión (con el resultado de la última verificación, que se ignora si la credencial ha cambiado).
* Asistente de inicio cuando no hay ningún canal configurado: por dónde empezar (Telegram → Mastodon → el resto).
* Estilos y scripts en ficheros propios (assets/css/admin.css, assets/js/admin.js), encolados solo en las pantallas del plugin; fuera el CSS pegado a dashicons y los style="" sueltos.
* Modo oscuro del escritorio, foco visible y etiquetas asociadas. Y ningún mensaje manda a una pestaña que no existe.


= 1.4.5 =
* El registro de canales ya no depende del classmap de Composer: si un canal es nuevo y nadie regeneró el classmap, se carga su fichero y se toma la clase declarada. Se comprueba en la propia batería de pruebas, con el autoloader real y un canal que el classmap no conoce.


= 1.4.4 =
* Arreglado: el plugin se quedaba **sin ningún canal**. El registro pedía el nombre de la clase en minúsculas («...\facebook») y el autoload de Composer no lo resolvía, así que no cargaba ningún canal: no publicaba en ninguna red y la pantalla de configuración no mostraba ningún campo de token (con el aviso «Configura al menos un token en la pestaña de Ajustes»).
* El registro ya no adivina nombres de clase: los deriva respetando su mayúscula real y, si un canal está declarado con otro nombre, también lo recoge. Se descartan la interfaz y las clases abstractas.
* Pruebas: nueva batería del registro de canales (nombres con su mayúscula real, los siete canales y lo que no debe registrarse).

= 1.4.3 =
* Fix: desactivar el plugin lanzaba un fatal («convoca_publisher_deactivation not found») porque el hook de desactivación se registraba sin el namespace. También afectaba a la desinstalación.
* Desinstalación: borra su tabla de cola, sus opciones y su cron; respeta el ajuste de conservar datos.

= 1.4.2 =
* Security: cifrado de tokens limitado a opciones propias (convoca_publisher_*) — antes cifraba cualquier opción *_token de terceros sin filtro de descifrado.

= 1.4.1 =
* Fix: callback de activación correcto (convoca_publisher_activation_check)

= 1.4.0 =
* New: Social media post scheduling
* New: Pre-publish validations (title, featured image)
* New: TikTok channel
* New: Google My Business channel
* Improvement: 42 unit tests, 148 assertions
* Improvement: Detailed guide per channel in the admin dashboard

== Screenshots ==

1. All channels at a glance: one card per network with its status (configured, missing token, verification error, needs reconnecting).
2. Everything for one channel on a single screen: credentials, its own message template, the connection check and the step-by-step guide.
3. General settings: automatic publishing, scheduling, moderation and the privacy notice.
4. Message templates: the global template and the variables you can use.
5. Test screen: publish a post to the selected channels to check the setup.
6. Moderation queue: review what is about to go out before it does.
7. Step-by-step guide for every network, linked from the channel it configures.

== Frequently Asked Questions ==

= Does it require Convoca Core? =

Yes. Convoca Publisher requires Convoca Core to be active.

= Which networks are supported? =

Facebook, Instagram, LinkedIn, Twitter/X, TikTok, Google My Business, Telegram, and Mastodon.

= How are tokens stored? =

Tokens are encrypted with AES-256-GCM. No third-party service stores your credentials.

== Upgrade Notice ==

= 1.4.0 =
* New features and compatibility improvements. Recommended update.
