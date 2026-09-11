=== Convoca Publisher ===
Contributors: josecarlosnietoramos
Tags: social-media, publishing, scheduling, telegram, mastodon
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.12.2
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

= 1.12.2 =
* Aseo interno: el criterio de «esto es un envío» vive en un solo sitio y el filtro por red recibe el mapa de cuentas directamente. Sin cambios de comportamiento.


= 1.12.1 =
* Los avisos (título vacío, sin imagen destacada, mensaje recortado) dejan de escribirse en el historial como si fueran envíos fallidos. Una entrada sin imagen destacada aparecía en rojo aunque se hubiera publicado bien.


= 1.12.0 =
* Historial con filtros por red, cuenta y resultado, y recuento de lo que se está viendo.
* Reintento individual de un envío que falló, desde su fila del historial, avisando del resultado.
* Los avisos internos del plugin dejan de aparecer como si fueran una red más.


= 1.11.1 =
* La pestaña Guía explica cómo funciona el plugin (varias cuentas por red, de dónde sale el mensaje, lo que admite cada red, la cola y qué pasa cuando algo falla) antes de los pasos de cada red.
* La documentación del plugin se pone al día: describía la versión 1.4.2, con cinco pestañas y los tokens en Configuración.


= 1.11.0 =
* Todo el código pasa el mismo control de estilo (46 de 46 ficheros): el proyecto queda con la línea base limpia, que es la que evita que cada cambio futuro traiga ruido.


= 1.10.2 =
* El widget cuenta **todos** los envíos parados, no solo los que caben en la lista: con ocho parados decía «5 envíos no han salido», que es mentir por omisión.


= 1.10.1 =
* El widget no repite un envío que además tiene un reintento vivo (es el mismo envío, no dos).
* Las filas de validación («no hay imagen destacada» y demás avisos del propio plugin) ya no se listan como si fueran envíos: en «lo último que salió» se leían como un fallo de la red, y no lo eran.


= 1.10.0 =
* Widget en el escritorio: lo siguiente que va a salir, lo que se ha quedado parado (con un enlace para ver qué pasó y reintentar) y lo último que salió con su resultado. Es lo que se ve sin entrar a buscar nada.


= 1.9.3 =
* Los plazos de caducidad miran la credencial que este plugin pide de verdad, que es un token pegado a mano y sin refresco: Facebook y LinkedIn 60 días, **TikTok 1 día** (su access token dura unas 24 h) y **Google My Business 1 día** (el de Google dura una hora). Antes esas dos últimas decían «no caduca», que era mentira.
* Twitter/X, Telegram y Mastodon siguen sin plazo: lo que se pega es un token de aplicación o de bot, que no caduca por su cuenta.
* Una prueba exige que las siete redes tengan plazo decidido: una red fuera de la tabla se trata como «no caduca» sin que nadie lo haya decidido.


= 1.9.2 =
* Aviso de credencial a punto de caducar: Facebook y LinkedIn caducan a los 60 días y el envío empezaba a fallar sin que nadie hubiera tocado nada. El canal lo dice **antes** («caduca pronto», «puede haber caducado»), con la fecha de la última comprobación y lo que dura el token de esa red.
* Las redes cuyo token no caduca por su cuenta (Telegram, Mastodon…) no generan avisos falsos, y una credencial que nunca se ha comprobado lo dice tal cual en vez de inventarse una fecha.


= 1.9.1 =
* Un programado que falla ya no se pierde: antes se borraba su marca aunque el envío no hubiera salido, así que no se reintentaba nunca. Ahora se le dan varias vueltas y, agotadas, se deja a la vista en la cola para darle salida a mano.
* La cola enseña lo que **se quedó atrás** (programados cuya hora pasó y no salieron: el cron de WordPress lo dispara el tráfico, y en un sitio tranquilo puede no llegar) con un botón para recuperarlos.
* Aviso por correo a quien administra el sitio cuando un envío no sale tras varios intentos, con la entrada, el motivo de cada red y dónde reintentarlo. Se puede apagar en Configuración.
* El cron de recuperación ya no abandona a los que fallaron más de una vez (que son justo los que necesitan otra vuelta).


= 1.9.0 =
* Compartir a mano en **una** cuenta concreta desde el editor, con un botón por cuenta, y «Compartir ahora» desde el listado de entradas.
* Mensaje propio de cada entrada (con las mismas variables), que manda sobre la plantilla de la cuenta y se guarda con la entrada.
* Vista previa real y contador de caracteres por red, con el peso que cada red da a los enlaces (X cuenta cualquiera como 23).
* Reglas por red antes de enviar en lugar de fallar al enviar: si no cabe se recorta conservando el enlace y queda avisado.


= 1.8.0 =
* Pantalla de la cola: calendario de mes y semana con los envíos de cada día (una entrada puede salir en varias cuentas: cada una es un envío), más la lista de lo que espera turno, lo que se ha atascado y lo último que salió.
* Arrastrar un envío a otro día para reprogramarlo, o cambiarle la hora y quitarlo de la cola desde la lista, sin entrar en la entrada.
* El espaciado entre envíos se elige en Configuración (sin espaciado, 15, 30 minutos, 1 o 2 horas) y hay un botón para recolocar la cola en el momento.


= 1.7.0 =
* La cola, como una sola cosa: los envíos programados (uno por cuenta), los reintentos y lo que ya salió, con su resultado.
* Espaciado entre envíos: intervalo mínimo configurable (media hora por defecto) para no soltar varias publicaciones en el mismo minuto. Lo que cae dentro del intervalo se recoloca solo, y la publicación programada publica el más antiguo y deja el resto para su turno.


= 1.6.0 =
* Varias cuentas por red: cada una con su nombre («Telegram — Centro Social», «Facebook — Grupo»), sus credenciales y su plantilla. El editor, la cola y el historial trabajan por cuenta y siguen diciendo a qué red pertenecen.
* La configuración que ya existía se convierte sola en una cuenta por red, sin perder tokens ni plantillas y sin tocar nada de lo publicado. Las opciones antiguas se quedan donde estaban.
* Las credenciales de cada cuenta se guardan cifradas (AES-256-GCM), como las de antes.
* Límite de cinco cuentas por red y borrado de una cuenta sin tocar las demás.


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

1. Channels by network, each with its accounts and their status (configured, missing token, verification error, needs reconnecting).
2. Everything for one account on a single screen: its name, credentials, its own message template, the connection check and the step-by-step guide.
3. General settings: automatic publishing, scheduling, moderation and the privacy notice.
4. Message templates: the global template and the variables you can use.
5. Test screen: publish a post to the selected channels to check the setup.
6. Moderation queue: review what is about to go out before it does.
7. Step-by-step guide for every network, linked from the channel it configures.
8. The queue: a month and week calendar of what is going out (drag an item to another day to reschedule it), the list of what is waiting, what got stuck and what went out last.

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
